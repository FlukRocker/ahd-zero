<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ReportTargetResolver;
use App\Http\Controllers\Controller;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\Member;
use App\Models\Report;
use App\Services\Chat\ModerationService;
use App\Services\Moderation\ChatMessageModerator;
use App\Services\Moderation\CommentModerator;
use App\Services\Moderation\ReportResolver;
use App\Support\ReportTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The admin Reports queue. Every action takes a `{type}:{id}` target so one
 * click resolves every report on the item. Item and ban actions reuse the same
 * services as the chat console and the comments page.
 */
class ReportManagementController extends Controller
{
    private const STATUSES = [Report::STATUS_OPEN, Report::STATUS_ACTIONED, Report::STATUS_DISMISSED];

    public function __construct(
        private readonly ReportTargetResolver $targets,
        private readonly ReportResolver $resolver,
    ) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : Report::STATUS_OPEN;

        $reports = Schema::hasTable('reports')
            ? Report::query()->where('status', $status)->latest('created_at')->latest('id')->limit(500)->get()
            : collect();

        $infos = [];
        foreach ($reports->groupBy(fn (Report $r): string => $r->target_type.':'.$r->target_id) as $key => $rows) {
            $infos[$key] = $this->lookup($rows->first()->target_type, $rows->first()->target_id);
        }

        $names = Member::query()
            ->whereIn('id', collect($infos)->pluck('authorMemberId')->filter()->all())
            ->pluck('name', 'id');

        $groups = $reports->groupBy(fn (Report $r): string => $r->target_type.':'.$r->target_id)
            ->map(function ($rows, string $key) use ($infos, $names, $status): array {
                $info = $infos[$key];

                return [
                    'type' => $info->type,
                    'id' => $info->id,
                    'excerpt' => $info->excerpt,
                    'author' => [
                        'name' => $info->authorMemberId === null ? null : ($names[$info->authorMemberId] ?? null),
                        'memberId' => $info->authorMemberId,
                    ],
                    'count' => $rows->count(),
                    'reasons' => $rows->pluck('reason')->unique()->values()->all(),
                    'notes' => $rows->pluck('note')->filter()->values()->all(),
                    'status' => $status,
                    'available' => $info->available,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/Reports', [
            'groups' => $groups,
            'filters' => ['status' => $status],
        ]);
    }

    public function delete(Request $request, string $target, ChatMessageModerator $chat, CommentModerator $comments): JsonResponse
    {
        [$type, $id] = $this->split($target);
        $info = $this->requireAvailable($type, $id);

        if ($type === Report::TYPE_CHAT) {
            $chat->delete(ChatMessage::query()->findOrFail((int) $info->id), $this->adminId($request));
        } else {
            $comments->delete($comments->find($id), $this->adminId($request));
        }

        return response()->json(['ok' => true]);
    }

    public function banChat(Request $request, string $target, ModerationService $moderation): JsonResponse
    {
        [$type, $id] = $this->split($target);
        $author = $this->requireAuthor($type, $id, Report::TYPE_CHAT);

        // A message can outlive its member row; do not create an orphan wallet for it.
        abort_unless(Member::query()->whereKey($author)->exists(), 422, 'The author of this item no longer exists.');

        $chatMember = ChatMember::query()->firstOrCreate(['member_id' => $author], ['rank' => 'member', 'balance' => 0]);
        $adminId = $this->adminId($request);
        $moderation->ban($chatMember, null, $this->reason($request, 255), null, $adminId);
        $this->resolver->resolveTarget($type, $id, Report::STATUS_ACTIONED, $adminId);

        return response()->json(['ok' => true]);
    }

    public function banSite(Request $request, string $target): JsonResponse
    {
        [$type, $id] = $this->split($target);
        $author = $this->requireAuthor($type, $id, Report::TYPE_CHAT);

        Member::query()->findOrFail($author)->update([
            'banned_at' => now(),
            'ban_reason' => $this->reason($request, 500),
        ]);
        $this->resolver->resolveTarget($type, $id, Report::STATUS_ACTIONED, $this->adminId($request));

        return response()->json(['ok' => true]);
    }

    public function banComment(Request $request, string $target, CommentModerator $comments): JsonResponse
    {
        [$type, $id] = $this->split($target);
        $this->requireAuthor($type, $id, Report::TYPE_COMMENT);

        $comments->deleteAndBan($comments->find($id), $this->reason($request, 500), false, $this->adminId($request));

        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request, string $target): JsonResponse
    {
        [$type, $id] = $this->split($target);

        $this->resolver->resolveTarget($type, $id, Report::STATUS_DISMISSED, $this->adminId($request));

        return response()->json(['ok' => true]);
    }

    /** @return array{0: string, 1: string} */
    private function split(string $target): array
    {
        [$type, $id] = explode(':', $target, 2) + [1 => ''];

        abort_unless(in_array($type, [Report::TYPE_COMMENT, Report::TYPE_CHAT], true) && $id !== '', 404);

        return [$type, $id];
    }

    /** A target that cannot be looked up (Mongo down, deleted) reads as unavailable. */
    private function lookup(string $type, string $id): ReportTarget
    {
        try {
            $found = $this->targets->find($type, $id);
        } catch (Throwable) {
            $found = null;
        }

        return $found ?? new ReportTarget($type, $id, null, '', false);
    }

    private function requireAvailable(string $type, string $id): ReportTarget
    {
        $info = $this->lookup($type, $id);

        abort_unless($info->available, 422, 'That item is gone. Dismiss the report instead.');

        return $info;
    }

    /** The member id of the item's author, for a ban of the right kind. */
    private function requireAuthor(string $type, string $id, string $expectedType): int
    {
        abort_unless($type === $expectedType, 422, 'That ban does not apply to this kind of item.');

        $author = $this->requireAvailable($type, $id)->authorMemberId;

        abort_if($author === null, 422, 'The author of this item is unknown, so it cannot be banned.');

        return $author;
    }

    private function reason(Request $request, int $max): ?string
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:'.$max]])['reason'] ?? null;

        return $reason === '' ? null : $reason;
    }

    private function adminId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
