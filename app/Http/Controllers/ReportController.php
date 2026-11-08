<?php

namespace App\Http\Controllers;

use App\Contracts\ReportTargetResolver;
use App\Http\Requests\StoreReportRequest;
use App\Models\Member;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request, ReportTargetResolver $targets): JsonResponse
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        if ($member->banned_at !== null) {
            return response()->json(['message' => 'You cannot report right now.'], 403);
        }

        // The table is created by hand on prod; until it exists say so plainly.
        if (! Schema::hasTable('reports')) {
            return response()->json(['message' => 'Reporting is not available yet.'], 503);
        }

        $data = $request->validated();
        try {
            $target = $targets->find($data['target_type'], $data['target_id']);
        } catch (Throwable) {
            return response()->json(['message' => 'Reporting is not available right now.'], 503);
        }

        if ($target === null || ! $target->available) {
            return response()->json(['message' => 'That item no longer exists.'], 404);
        }

        if ($target->authorMemberId === $member->id) {
            return response()->json(['message' => 'You cannot report your own message.'], 422);
        }

        // createOrFirst leans on the unique key, so two concurrent identical
        // reports cannot both insert.
        $report = Report::query()->createOrFirst(
            ['member_id' => $member->id, 'target_type' => $target->type, 'target_id' => $target->id],
            ['reason' => $data['reason'], 'note' => $data['note'] ?? null, 'status' => Report::STATUS_OPEN],
        );

        return response()->json(['ok' => true], $report->wasRecentlyCreated ? 201 : 200);
    }
}
