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
use App\Rules\NoLinks;
use App\Services\Chat\ChatImage;
use App\Services\Chat\ChatService;
use App\Services\Moderation\ReportResolver;
use App\Support\Chat\Bbcode;
use App\Support\ChatText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
            'canPost' => $member !== null,
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

    public function store(Request $request, ChatImage $images): JsonResponse
    {
        $request->merge(['body' => ChatText::clean((string) $request->input('body', ''))]);

        $hasImage = $request->hasFile('image');

        $validated = $request->validate([
            // Text may be empty only when a picture is attached. 450 leaves room
            // for the appended image token inside a 500-char body.
            'body' => [$hasImage ? 'nullable' : 'required', 'string', $hasImage ? 'max:450' : 'max:500', new NoLinks,
                // BBCode tags can split a link ("evil[b][/b].com"), so check the rendered text too.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value)) {
                        (new NoLinks)->validate($attribute, implode('', array_column(Bbcode::parse($value), 'v')), $fail);
                    }
                },
                // Only the server writes [img]; a typed one could not point at anyone's file anyway, but keep the invariant.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && stripos($value, '[img') !== false) {
                        $fail('ไม่สามารถใช้แท็ก [img] ได้ — กดปุ่มแนบรูปแทน');
                    }
                },
            ],
            'image' => ['nullable', 'file', 'max:3072'],
            // Optional: a page left open across the deploy posts without it.
            'room_id' => ['nullable', 'integer', 'exists:chat_rooms,id,active,1'],
        ]);
        $roomId = isset($validated['room_id']) ? (int) $validated['room_id'] : ChatRoom::lobby()->id;

        $member = $this->member($request);
        $chatMember = $this->chatMember($member);

        if (! $chatMember->canSpeak()) {
            return response()->json(['message' => $this->refusal($chatMember)], 403);
        }

        $body = (string) ($validated['body'] ?? '');

        if ($hasImage) {
            // Images cost GD time and disk, so they get a tighter limit than text.
            $key = 'chat-image:'.$member->id;

            if (RateLimiter::tooManyAttempts($key, 5)) {
                return response()->json(['message' => 'ส่งรูปเร็วเกินไป รอสักครู่'], 429);
            }

            RateLimiter::hit($key, 60);

            try {
                $name = $images->store($request->file('image'));
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage(), 'errors' => ['image' => [$e->getMessage()]]], 422);
            }

            $body = trim($body.' '.ChatImage::token($name));
        }

        $message = ChatMessage::create([
            'member_id' => $member->id,
            'body' => $body,
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
