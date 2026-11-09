<?php

namespace App\Support;

/**
 * What a report points at, as seen by the report endpoint and the admin queue.
 * `available` is false for deleted items and items from another site.
 */
final readonly class ReportTarget
{
    public function __construct(
        public string $type,
        public string $id,
        public ?int $authorMemberId,
        public string $excerpt,
        public bool $available,
        public ?string $image = null,
    ) {}
}
