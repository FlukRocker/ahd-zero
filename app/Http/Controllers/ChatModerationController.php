<?php

namespace App\Http\Controllers;

use App\Enums\ChatRank;
use App\Models\ChatMember;
use App\Models\Member;
use App\Policies\ChatPolicy;
use App\Services\Chat\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * In-room moderation. Members only: a site admin moderates from the dashboard,
 * so every route under /chat stays on one guard instead of threading two
 * identity types through the policy.
 */
class ChatModerationController extends Controller
{
    public function __construct(
        private readonly ChatPolicy $policy,
        private readonly ModerationService $moderation,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['gag', 'ungag', 'kick', 'ban', 'unban', 'rank'])],
            'target_uuid' => ['required', 'string'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'reason' => ['nullable', 'string', 'max:255'],
            // required_if, because the 'rank' arm below calls ChatRank::from()
            // and that throws on an empty string.
            'rank' => ['required_if:action,rank', 'nullable', Rule::enum(ChatRank::class)],
        ]);

        /** @var Member $actor */
        $actor = Auth::guard('member')->user();
        $actorRow = ChatMember::query()->firstOrCreate(['member_id' => $actor->id], ['rank' => 'member', 'balance' => 0]);

        $target = Member::query()->where('uuid', $validated['target_uuid'])->firstOrFail();
        $targetRow = ChatMember::query()->firstOrCreate(['member_id' => $target->id], ['rank' => 'member', 'balance' => 0]);
        $targetRow->setRelation('member', $target);

        if (! $this->policy->canActOn($actorRow->rank, $targetRow->rank)) {
            return response()->json(['message' => 'You cannot act on that member.'], 403);
        }

        // canActOn above judged the target's CURRENT rank; canSetRank only
        // judges the NEW rank, so both are required for a rank change.
        $allowed = match ($validated['action']) {
            'gag', 'ungag' => $this->policy->canGag($actorRow->rank),
            'kick' => $this->policy->canKick($actorRow->rank),
            'ban', 'unban' => $this->policy->canBan($actorRow->rank),
            'rank' => $this->policy->canSetRank($actorRow->rank, ChatRank::from($validated['rank'])),
            default => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Your rank does not allow that.'], 403);
        }

        match ($validated['action']) {
            'gag' => $this->moderation->gag($targetRow, $validated['minutes'] ?? 10, $actor->id, null),
            'ungag' => $this->moderation->ungag($targetRow, $actor->id, null),
            'kick' => $this->moderation->kick($targetRow, $actor->id, null),
            'ban' => $this->moderation->ban($targetRow, $validated['minutes'] ?? null, $validated['reason'] ?? null, $actor->id, null),
            'unban' => $this->moderation->unban($targetRow, $actor->id, null),
            'rank' => $this->moderation->setRank($targetRow, ChatRank::from($validated['rank']), $actor->id, null),
            default => throw new \LogicException('Unvalidated moderation action.'),
        };

        return response()->json(['ok' => true]);
    }
}
