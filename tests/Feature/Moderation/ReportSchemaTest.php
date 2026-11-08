<?php

namespace Tests\Feature\Moderation;

use App\Models\Member;
use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_table_has_the_unique_member_target_key(): void
    {
        $member = Member::forceCreate(['uuid' => (string) Str::uuid(), 'name' => 'a', 'email' => 'a@example.test', 'password' => 'x']);
        $row = ['member_id' => $member->id, 'target_type' => 'comment', 'target_id' => 'abc', 'reason' => 'spam', 'status' => 'open'];

        Report::create($row);

        $this->expectException(QueryException::class);
        Report::create($row);
    }
}
