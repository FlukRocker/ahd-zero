<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Number of member reports waiting for an admin. The table is created by hand
 * on prod, so until it exists this is simply zero rather than an error.
 */
class OpenReports
{
    public static function count(): int
    {
        if (! Schema::hasTable('reports')) {
            return 0;
        }

        return DB::table('reports')->where('status', 'open')->count();
    }
}
