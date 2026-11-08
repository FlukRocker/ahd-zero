<?php

namespace App\Services\Moderation;

use App\Models\Report;
use Illuminate\Support\Facades\Schema;

class ReportResolver
{
    /**
     * Close every open report on one item. Returns the rows changed.
     *
     * A no-op while the reports table does not exist (it is created by hand
     * on prod), so every delete path can call this without a deploy order.
     */
    public function resolveTarget(string $type, string $id, string $status, ?int $adminId): int
    {
        if (! Schema::hasTable('reports')) {
            return 0;
        }

        return Report::query()
            ->where('target_type', $type)
            ->where('target_id', $id)
            ->where('status', Report::STATUS_OPEN)
            ->update(['status' => $status, 'resolved_by' => $adminId, 'resolved_at' => now()]);
    }
}
