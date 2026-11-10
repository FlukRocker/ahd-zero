<?php

namespace App\Http\Controllers\Admin;

use App\Events\Chat\MessagesPurged;
use App\Exceptions\NotOwnedException;
use App\Exceptions\StoreException;
use App\Http\Controllers\Controller;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatRoom;
use App\Models\ChatTransaction;
use App\Models\Member;
use App\Services\Chat\ChatService;
use App\Services\Chat\ModerationService;
use App\Services\Chat\PresenceService;
use App\Services\Chat\StoreService;
use App\Services\Moderation\ReportResolver;
use App\Support\Chat\ChatPins;
use App\Support\Chat\ChatSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Extra dashboard tools for the chat: live roster, stats, message search,
 * member detail and clean-up actions, plus the chat settings (announcement,
 * word filter, room lock / slow mode).
 *
 * Read endpoints are separate JSON calls (not Inertia props) so the page's
 * reload after every action stays cheap.
 *
 * chat_moderation_log.action and chat_transactions.reason are ENUM columns in
 * production (see ahd-admin), so new operations reuse an existing value and say
 * what they really are in `meta.op`.
 */
class ChatToolsController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly StoreService $store,
        private readonly ChatService $chat,
        private readonly ReportResolver $reports,
    ) {}

    /** Everyone online site-wide, with member ids for the action buttons. */
    public function online(PresenceService $presence): JsonResponse
    {
        return response()->json(['online' => $presence->online(withIds: true)]);
    }

    public function stats(PresenceService $presence): JsonResponse
    {
        return response()->json(Cache::remember('chat:admin:stats', 60, function () use ($presence): array {
            $days = [];

            for ($i = 13; $i >= 0; $i--) {
                $days[now()->subDays($i)->toDateString()] = 0;
            }

            ChatMessage::query()
                ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->groupBy('d')
                ->get()
                ->each(function ($row) use (&$days): void {
                    if (array_key_exists((string) $row->d, $days)) {
                        $days[(string) $row->d] = (int) $row->c;
                    }
                });

            $weekAgo = now()->subDays(7);
            $top = ChatMessage::query()
                ->selectRaw('member_id, COUNT(*) as c')
                ->where('created_at', '>=', $weekAgo)
                ->groupBy('member_id')
                ->orderByDesc('c')
                ->limit(5)
                ->get();
            $names = Member::query()->whereIn('id', $top->pluck('member_id'))->pluck('name', 'id');

            return [
                'perDay' => collect($days)->map(fn (int $c, string $d): array => ['day' => $d, 'count' => $c])->values()->all(),
                'activeMembers7d' => (int) ChatMessage::query()->where('created_at', '>=', $weekAgo)->distinct()->count('member_id'),
                'topChatters' => $top->map(fn ($r): array => ['name' => (string) ($names[$r->member_id] ?? '—'), 'count' => (int) $r->c])->all(),
                'coinsInCirculation' => (int) ChatMember::query()->sum('balance'),
                'totalMessages' => ChatMessage::query()->count(),
                'bannedNow' => ChatMember::query()->whereNotNull('banned_at')
                    ->where(fn ($q) => $q->whereNull('banned_until')->orWhere('banned_until', '>', now()))->count(),
                'onlineNow' => count($presence->online()),
            ];
        }));
    }

    public function messages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'member' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $member = trim((string) ($validated['member'] ?? ''));

        if (mb_strlen($q) < 2 && mb_strlen($member) < 2 && ! isset($validated['room'])) {
            return response()->json(['message' => 'Type at least 2 characters or pick a room.', 'errors' => ['q' => ['Type at least 2 characters or pick a room.']]], 422);
        }

        $query = ChatMessage::query()->with('member:id,name')->orderByDesc('id')->limit(100);

        if (mb_strlen($q) >= 2) {
            $query->whereRaw("body LIKE ? ESCAPE '!'", ['%'.$this->like($q).'%']);
        }

        if (mb_strlen($member) >= 2) {
            $ids = Member::query()->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$this->like($member).'%'])->limit(200)->pluck('id');
            $query->whereIn('member_id', $ids);
        }

        if (isset($validated['room'])) {
            $query->where('room_id', (int) $validated['room']);
        }

        return response()->json(['messages' => $query->get()->map(fn (ChatMessage $m): array => [
            'id' => $m->id,
            'body' => $m->body,
            'room_id' => (int) $m->room_id,
            'created_at' => (string) $m->created_at?->toIso8601String(),
            'member' => $m->member === null ? null : ['id' => (int) $m->member_id, 'name' => $m->member->name],
        ])->values()->all()]);
    }

    public function member(int $id): JsonResponse
    {
        $member = Member::query()->findOrFail($id);
        $chat = ChatMember::query()->find($id);

        return response()->json([
            'id' => (int) $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'rank' => $chat?->rank->value ?? 'member',
            'balance' => (int) ($chat?->balance ?? 0),
            'banned' => $chat?->isBanned() ?? false,
            'ban_reason' => $chat?->ban_reason,
            'gagged' => $chat?->isGagged() ?? false,
            'gagged_until' => $chat?->gagged_until?->toIso8601String(),
            'cosmetics' => ChatCosmeticMember::query()->where('member_id', $id)->with('cosmetic:id,name,type')->get()
                ->filter(fn ($row) => $row->cosmetic !== null)
                ->map(fn ($row): array => [
                    'id' => (int) $row->cosmetic_id,
                    'name' => $row->cosmetic->name,
                    'type' => $row->cosmetic->type->value,
                    'equipped' => (bool) $row->equipped,
                ])->values()->all(),
            'messages' => ChatMessage::query()->where('member_id', $id)->orderByDesc('id')->limit(10)->get(['id', 'body', 'room_id', 'created_at'])
                ->map(fn ($m): array => ['id' => $m->id, 'body' => $m->body, 'created_at' => (string) $m->created_at?->toIso8601String()])->all(),
            'transactions' => ChatTransaction::query()->where('member_id', $id)->orderByDesc('id')->limit(10)->get()
                ->map(fn ($t): array => ['delta' => (int) $t->delta, 'balance_after' => (int) $t->balance_after, 'note' => $t->note, 'created_at' => (string) $t->created_at?->toIso8601String()])->all(),
        ]);
    }

    /** Everyone who owns one cosmetic, newest first (capped), with how they got it. */
    public function cosmeticOwners(ChatCosmetic $cosmetic): JsonResponse
    {
        $rows = ChatCosmeticMember::query()->where('cosmetic_id', $cosmetic->id)->orderByDesc('acquired_at')->limit(500)->get();
        $names = Member::query()->whereIn('id', $rows->pluck('member_id'))->pluck('name', 'id');

        return response()->json([
            'total' => ChatCosmeticMember::query()->where('cosmetic_id', $cosmetic->id)->count(),
            'owners' => $rows->map(fn ($row): array => [
                'member_id' => (int) $row->member_id,
                'name' => (string) ($names[$row->member_id] ?? 'Member #'.$row->member_id),
                'source' => (string) $row->source,
                'equipped' => (bool) $row->equipped,
                'acquired_at' => $row->acquired_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function kick(Request $request, int $id): JsonResponse
    {
        $this->moderation->kick($this->wallet($id), null, (int) $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function gag(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'ungag' => ['sometimes', 'boolean'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
        ]);
        $target = $this->wallet($id);
        $adminId = (int) $request->user()->id;

        if ($validated['ungag'] ?? false) {
            $this->moderation->ungag($target, null, $adminId);
        } else {
            $this->moderation->gag($target, (int) ($validated['minutes'] ?? 10), null, $adminId);
        }

        return response()->json(['ok' => true]);
    }

    public function takeCoins(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        Member::query()->findOrFail($id);

        try {
            // Not wrapped in a transaction: see StoreService::debitCoins.
            $this->store->debitCoins($id, $validated['amount'], (int) $request->user()->id, $validated['note'] ?? null);
        } catch (StoreException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['amount' => [$e->getMessage()]]], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function revokeCosmetic(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['cosmetic_id' => ['required', 'integer', 'exists:chat_cosmetics,id']]);
        Member::query()->findOrFail($id);

        $removed = $this->store->revokeCosmetic($id, ChatCosmetic::query()->findOrFail($validated['cosmetic_id']), (int) $request->user()->id);

        if ($removed) {
            $this->forgetAllHistory(); // it may have been equipped: rebuild the cached styles
        }

        return response()->json(['ok' => $removed]);
    }

    /** Equip (replacing the same slot) or unequip a cosmetic the member owns. */
    public function setEquipped(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cosmetic_id' => ['required', 'integer', 'exists:chat_cosmetics,id'],
            'equipped' => ['required', 'boolean'],
        ]);
        Member::query()->findOrFail($id);
        $cosmetic = ChatCosmetic::query()->findOrFail($validated['cosmetic_id']);

        if ($validated['equipped']) {
            try {
                $this->store->equip($id, $cosmetic);
            } catch (NotOwnedException|StoreException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            $changed = ChatCosmeticMember::query()
                ->where('member_id', $id)->where('cosmetic_id', $cosmetic->id)
                ->update(['equipped' => false]);

            if ($changed === 0) {
                return response()->json(['message' => 'Member does not own that item.'], 422);
            }
        }

        $this->forgetAllHistory(); // cached lines carry the rendered style

        return response()->json(['ok' => true]);
    }

    /** Delete every message one member has written, in one pass. */
    public function purgeMemberMessages(Request $request, int $id): JsonResponse
    {
        $member = Member::query()->findOrFail($id);
        $adminId = (int) $request->user()->id;
        $ids = ChatMessage::query()->where('member_id', $id)->pluck('id');
        $rooms = ChatMessage::query()->where('member_id', $id)->distinct()->pluck('room_id');

        foreach ($ids->chunk(500) as $chunk) {
            ChatMessage::query()->whereIn('id', $chunk)->update(['deleted_by_admin_id' => $adminId, 'deleted_at' => now()]);
        }

        $this->reports->resolveTargets('chat_message', $ids, 'actioned', $adminId);

        if ($ids->isNotEmpty()) {
            ChatModerationLog::create([
                'admin_id' => $adminId,
                'target_member_id' => $id,
                'action' => 'delete_message',
                'meta' => ['op' => 'purge_member', 'count' => $ids->count()],
            ]);
        }

        $this->afterBulkDelete($rooms->map(fn ($r): int => (int) $r)->all(), $member->uuid);

        return response()->json(['ok' => true, 'deleted' => $ids->count()]);
    }

    /** Remove every message in a room. The room itself stays. */
    public function clearRoom(Request $request, ChatRoom $room): JsonResponse
    {
        $adminId = (int) $request->user()->id;
        $ids = ChatMessage::query()->where('room_id', $room->id)->pluck('id');
        $count = $ids->count();
        $this->reports->resolveTargets('chat_message', $ids, 'actioned', $adminId);

        ChatMessage::query()->where('room_id', $room->id)->update(['deleted_by_admin_id' => $adminId, 'deleted_at' => now()]);

        // chat_moderation_log needs a target member, which a room clear has none of.
        Log::info('chat room cleared', ['room' => $room->id, 'admin' => $adminId, 'count' => $count]);

        $this->afterBulkDelete([(int) $room->id], null);

        return response()->json(['ok' => true, 'deleted' => $count]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'announcement' => ChatSettings::announcement() ?? '',
            'words' => implode("\n", ChatSettings::words()),
            'rooms' => ChatRoom::query()->orderBy('sort')->orderBy('id')->get(['id', 'name'])->map(fn ($r): array => [
                'id' => (int) $r->id, 'name' => $r->name, ...ChatSettings::room((int) $r->id),
            ])->all(),
        ]);
    }

    public function updateAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate(['announcement' => ['nullable', 'string', 'max:'.ChatSettings::ANNOUNCEMENT_MAX]]);
        ChatSettings::setAnnouncement((string) ($validated['announcement'] ?? ''));

        return response()->json(['ok' => true]);
    }

    public function updateWords(Request $request): JsonResponse
    {
        $validated = $request->validate(['words' => ['nullable', 'string', 'max:20000']]);
        ChatSettings::setWords((string) ($validated['words'] ?? ''));

        return response()->json(['ok' => true, 'count' => count(ChatSettings::words())]);
    }

    public function updateRoomControls(Request $request, ChatRoom $room): JsonResponse
    {
        $validated = $request->validate([
            'locked' => ['required', 'boolean'],
            'slow' => ['required', 'integer', 'min:0', 'max:'.ChatSettings::SLOW_MAX],
        ]);
        ChatSettings::setRoom((int) $room->id, (bool) $validated['locked'], (int) $validated['slow']);

        return response()->json(['ok' => true]);
    }

    /** @param  list<int>  $roomIds */
    private function afterBulkDelete(array $roomIds, ?string $uuid): void
    {
        foreach ($roomIds as $roomId) {
            $this->chat->forgetHistory($roomId);

            // A pin pointing at a message that no longer exists must go too.
            $pinned = ChatPins::get($roomId);

            if ($pinned !== null && ! ChatMessage::query()->whereKey($pinned)->exists()) {
                ChatPins::clear($roomId);
                \App\Events\Chat\MessagePinned::dispatch($roomId, null);
            }

            MessagesPurged::dispatch($roomId, $uuid);
        }
    }

    private function forgetAllHistory(): void
    {
        foreach (ChatRoom::query()->pluck('id') as $roomId) {
            $this->chat->forgetHistory((int) $roomId);
        }
    }

    private function wallet(int $id): ChatMember
    {
        Member::query()->findOrFail($id);

        return ChatMember::query()->firstOrCreate(['member_id' => $id], ['rank' => 'member', 'balance' => 0]);
    }

    private function like(string $value): string
    {
        // '!' is the escape character (ESCAPE '!'), which behaves the same on MariaDB and sqlite.
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
