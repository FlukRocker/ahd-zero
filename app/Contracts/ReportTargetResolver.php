<?php

namespace App\Contracts;

use App\Support\ReportTarget;

/**
 * Looks up a reportable item. Comments live in Mongo, which tests do not have,
 * so everything goes through this seam.
 */
interface ReportTargetResolver
{
    /** Null when the item does not exist. */
    public function find(string $type, string $id): ?ReportTarget;
}
