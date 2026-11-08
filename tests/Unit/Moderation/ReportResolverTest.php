<?php

namespace Tests\Unit\Moderation;

use App\Models\Member;
use App\Models\Report;
use App\Services\Moderation\ReportResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportResolverTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $name): Member
    {
        return Member::forceCreate([
            'uuid' => (string) Str::uuid(), 'name' => $name, 'email' => $name.'@example.test', 'password' => 'x',
        ]);
    }

    private function report(Member $by, string $type, string $id, string $status = 'open'): Report
    {
        return Report::create([
            'member_id' => $by->id, 'target_type' => $type, 'target_id' => $id, 'reason' => 'spam', 'status' => $status,
        ]);
    }

    public function test_resolve_target_closes_every_open_report_on_that_target_only(): void
    {
        $a = $this->member('a');
        $b = $this->member('b');
        $one = $this->report($a, 'chat_message', '5');
        $two = $this->report($b, 'chat_message', '5');
        $other = $this->report($a, 'chat_message', '6');
        $sameIdOtherType = $this->report($a, 'comment', '5');

        $changed = (new ReportResolver)->resolveTarget('chat_message', '5', 'actioned', 9);

        $this->assertSame(2, $changed);
        $this->assertSame('actioned', $one->fresh()->status);
        $this->assertSame(9, (int) $one->fresh()->resolved_by);
        $this->assertNotNull($one->fresh()->resolved_at);
        $this->assertSame('actioned', $two->fresh()->status);
        $this->assertSame('open', $other->fresh()->status);
        $this->assertSame('open', $sameIdOtherType->fresh()->status);
    }

    public function test_already_resolved_reports_are_untouched(): void
    {
        $a = $this->member('a');
        $done = $this->report($a, 'chat_message', '5', 'dismissed');

        $changed = (new ReportResolver)->resolveTarget('chat_message', '5', 'actioned', 9);

        $this->assertSame(0, $changed);
        $this->assertSame('dismissed', $done->fresh()->status);
    }

    public function test_resolve_target_is_a_noop_when_the_table_is_missing(): void
    {
        Schema::dropIfExists('reports');

        $this->assertSame(0, (new ReportResolver)->resolveTarget('chat_message', '5', 'actioned', 9));
    }
}
