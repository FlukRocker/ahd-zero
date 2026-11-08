<?php

namespace App\Http\Controllers;

use App\Enums\ChatRank;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\MessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatRoom;
use App\Models\Member;
use App\Policies\ChatPolicy;
use App\Rules\NoExternalLinks;
use App\Services\Chat\ChatService;
use App\Services\Moderation\ReportResolver;
use App\Support\ChatText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly ChatPolicy $policy,
        private readonly ReportResolver $reports,
    ) {}

    public function show(?string $slug = null): View
    {
        $room = $slug === null
            ? ChatRoom::lobby()
            : ChatRoom::query()->active()->where('slug', $slug)->firstOrFail();

        return view('chat', [...$this->state($room), 'rooms' => ChatRoom::activeOrdered()]);
    }

    /** Lobby state as JSON for the floating mini chat, which loads it on first open. */
    public function mini(): JsonResponse
    {
        $room = ChatRoom::lobby();
        $member = Auth::guard('member')->user();
        $state = $this->state($room);

        return response()->json([
            'history' => $state['history'],
            'room' => ['id' => $room->id, 'slug' => $room->slug, 'name' => $room->name],
            'me' => $member?->uuid,
            'canPost' => $member !== null && $member->hasVerifiedEmail(),
            'myRank' => $state['myRank'],
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array{history: array<int, mixed>, myRank: ?string, room: ChatRoom}
     */
    private function state(ChatRoom $room): array
    {
        $member = Auth::guard('member')->user();

        // Fresh query, not derived from cached history. Null for guests;
        // 'member' when there is no chat_members row yet.
        $myRank = $member === null
            ? null
            : ChatMember::query()->whereKey($member->id)->first()?->rank->value ?? ChatRank::Member->value;

        return [
            'history' => $this->chat->history($room->id),
            'myRank' => $myRank,
            'room' => $room,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['body' => ChatText::clean((string) $request->input('body', ''))]);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:500', new NoExternalLinks],
            // Optional: a page left open across the deploy posts without it.
            'room_id' => ['nullable', 'integer', 'exists:chat_rooms,id,active,1'],
        ]);
        $roomId = isset($validated['room_id']) ? (int) $validated['room_id'] : ChatRoom::lobby()->id;

        $member = $this->member($request);
        $chatMember = $this->chatMember($member);

        if (! $chatMember->canSpeak()) {
            return response()->json(['message' => $this->refusal($chatMember)], 403);
        }

        $message = ChatMessage::create([
            'member_id' => $member->id,
            'body' => $validated['body'],
            'room_id' => $roomId,
        ]);
        $message->setRelation('member', $member);

        $record = $this->chat->record($message);
        $this->chat->remember($roomId, $record);

        MessageSent::dispatch($roomId, $record);

        return response()->json($record, 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $message = ChatMessage::query()->findOrFail($id);
        $member = $this->member($request);

        $actorRank = $this->chatMember($member)->rank;
        $author = ChatMember::query()->find($message->member_id);
        $authorRank = $author === null ? ChatRank::Member : $author->rank;

        if (! $this->policy->canDeleteMessage($actorRank, $authorRank, $message->member_id === $member->id)) {
            return response()->json(['message' => 'You cannot delete that message.'], 403);
        }

        $message->deleted_by = $member->id;
        $message->save();
        $message->delete();

        // Drop the whole cached list rather than surgically removing one blob:
        // the next read rebuilds 200 rows from MySQL, excluding soft-deleted
        // ones, so a deleted message cannot reappear.
        $roomId = (int) $message->room_id;
        $this->chat->forgetHistory($roomId);

        ChatModerationLog::create([
            'actor_member_id' => $member->id,
            'target_member_id' => $message->member_id,
            'action' => 'delete_message',
            'meta' => ['message_id' => $message->id, 'room_id' => $roomId, 'own' => $message->member_id === $member->id],
        ]);

        MessageDeleted::dispatch($roomId, $message->id);

        $this->reports->resolveTarget('chat_message', (string) $message->id, 'actioned', null);

        return response()->json(['deleted' => true]);
    }

    private function member(Request $request): Member
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return $member;
    }

    /** Create the row on demand — a member who has never spoken has none. */
    private function chatMember(Member $member): ChatMember
    {
        return ChatMember::query()->firstOrCreate(['member_id' => $member->id], ['rank' => 'member', 'balance' => 0]);
    }

    private function refusal(ChatMember $chatMember): string
    {
        return match (true) {
            $chatMember->isBanned() => 'You are banned from the chat.',
            $chatMember->isGagged() => 'You are gagged and cannot send messages right now.',
            default => 'You were kicked. Try again in a moment.',
        };
    }
}
