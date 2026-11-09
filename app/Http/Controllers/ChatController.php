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
use App\Services\Chat\ChatService;
use App\Services\Chat\PinService;
use App\Services\Moderation\ReportResolver;
use App\Support\Chat\Bbcode;
use App\Support\Chat\ChatRules;
use App\Support\Chat\ReplyToken;
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
            'canPost' => $member !== null,
            'myRank' => $state['myRank'],
            'meName' => $member?->name,
            'smilies' => $state['smilies'],
            'pinned' => $state['pinned'],
            'rules' => ChatRules::forClient(),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array{history: array<int, mixed>, myRank: ?string, room: ChatRoom, pinned: array<string, mixed>|null, smilies: array<string, string>}
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
            'pinned' => $this->chat->pinned($room->id),
            'smilies' => $member === null ? [] : $this->chat->smiliesFor((int) $member->id),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['body' => ChatText::clean((string) $request->input('body', ''))]);

        $validated = $request->validate([
            // A reply adds a 14-char server token to the stored body, so leave room.
            'body' => ['required', 'string', $request->filled('reply_to') ? 'max:480' : 'max:500',
                // Links are blocked everywhere except a vetted [img] embed. Screen
                // only the text a reader sees outside embeds; BBCode tags and
                // images can split a link ("evil[b][/b].com"), so the pieces are
                // joined before the check.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    // Only the server writes the reply token.
                    if (stripos($value, '[re=') !== false) {
                        $fail('ไม่สามารถใช้แท็ก [re=] ได้');

                        return;
                    }

                    $segments = Bbcode::parse($value);

                    if (Bbcode::imageCount($segments) > Bbcode::MAX_IMAGES) {
                        $fail('ใส่รูปได้ไม่เกิน '.Bbcode::MAX_IMAGES.' รูปต่อข้อความ');

                        return;
                    }

                    (new NoLinks)->validate($attribute, Bbcode::plainText($segments), $fail);
                },
            ],
            'reply_to' => ['nullable', 'integer', 'min:1'],
            // Optional: a page left open across the deploy posts without it.
            'room_id' => ['nullable', 'integer', 'exists:chat_rooms,id,active,1'],
        ]);
        $roomId = isset($validated['room_id']) ? (int) $validated['room_id'] : ChatRoom::lobby()->id;

        $member = $this->member($request);
        $chatMember = $this->chatMember($member);

        if (! $chatMember->canSpeak()) {
            return response()->json(['message' => $this->refusal($chatMember)], 403);
        }

        $body = (string) $validated['body'];

        if (isset($validated['reply_to'])) {
            $target = ChatMessage::query()->where('room_id', $roomId)->find((int) $validated['reply_to']);

            if ($target === null) {
                return response()->json([
                    'message' => 'ข้อความที่ตอบกลับไม่มีอยู่แล้ว',
                    'errors' => ['reply_to' => ['ข้อความที่ตอบกลับไม่มีอยู่แล้ว']],
                ], 422);
            }

            $body = ReplyToken::token((int) $target->id).$body;
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

    public function pin(Request $request, int $id, PinService $pins): JsonResponse
    {
        $member = $this->member($request);

        if (! $this->policy->canPin($this->chatMember($member)->rank)) {
            return response()->json(['message' => 'Your rank does not allow that.'], 403);
        }

        $pins->pin(ChatMessage::query()->findOrFail($id), (int) $member->id, null);

        return response()->json(['ok' => true]);
    }

    public function unpin(Request $request, int $id, PinService $pins): JsonResponse
    {
        $member = $this->member($request);

        if (! $this->policy->canPin($this->chatMember($member)->rank)) {
            return response()->json(['message' => 'Your rank does not allow that.'], 403);
        }

        $pins->unpin((int) ChatMessage::query()->findOrFail($id)->room_id, (int) $member->id, null, $id);

        return response()->json(['ok' => true]);
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
        app(PinService::class)->forgetMessage($message);

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
