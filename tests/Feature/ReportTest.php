<?php

namespace Tests\Feature;

use App\Contracts\ReportTargetResolver;
use App\Models\ChatMessage;
use App\Models\Member;
use App\Models\Report;
use App\Support\ReportTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Member $reporter;

    private Member $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->reporter = $this->member('Kai');
        $this->author = $this->member('Rin');
    }

    private function member(string $name, bool $verified = true): Member
    {
        return Member::create([
            'name' => $name,
            'email' => Str::lower($name).'@example.test',
            'password' => 'password123!',
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    private function message(?Member $by = null): ChatMessage
    {
        return ChatMessage::create(['member_id' => ($by ?? $this->author)->id, 'body' => 'rude']);
    }

    /** @param array<string, mixed> $over */
    private function body(string $id, array $over = []): array
    {
        return array_merge(['target_type' => 'chat_message', 'target_id' => $id, 'reason' => 'abuse'], $over);
    }

    public function test_a_verified_member_can_report_a_chat_message(): void
    {
        $message = $this->message();

        $this->actingAs($this->reporter, 'member')
            ->postJson('/reports', $this->body((string) $message->id, ['note' => 'insults']))
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $this->assertDatabaseHas('reports', [
            'member_id' => $this->reporter->id,
            'target_type' => 'chat_message',
            'target_id' => (string) $message->id,
            'reason' => 'abuse',
            'note' => 'insults',
            'status' => 'open',
        ]);
    }

    public function test_reporting_twice_creates_one_row_and_still_succeeds(): void
    {
        $message = $this->message();
        $this->actingAs($this->reporter, 'member')->postJson('/reports', $this->body((string) $message->id))->assertCreated();

        $this->postJson('/reports', $this->body((string) $message->id, ['reason' => 'spam']))
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertSame(1, Report::query()->count());
        $this->assertSame('abuse', Report::query()->first()->reason);
    }

    public function test_you_cannot_report_your_own_message(): void
    {
        $mine = $this->message($this->reporter);

        $this->actingAs($this->reporter, 'member')->postJson('/reports', $this->body((string) $mine->id))->assertStatus(422);

        $this->assertSame(0, Report::query()->count());
    }

    public function test_unknown_or_deleted_or_other_site_target_is_404_not_500(): void
    {
        $gone = $this->message();
        $gone->delete();
        $this->app->bind(ReportTargetResolver::class, fn () => new class implements ReportTargetResolver
        {
            public function find(string $type, string $id): ?ReportTarget
            {
                return match ($id) {
                    'othersite' => new ReportTarget('comment', $id, null, 'x', false),
                    default => null,
                };
            }
        });
        $this->actingAs($this->reporter, 'member');

        $this->postJson('/reports', $this->body('999999'))->assertNotFound();
        $this->postJson('/reports', $this->body((string) $gone->id))->assertNotFound();
        $this->postJson('/reports', $this->body('othersite', ['target_type' => 'comment']))->assertNotFound();
        $this->postJson('/reports', $this->body('missing', ['target_type' => 'comment']))->assertNotFound();

        $this->assertSame(0, Report::query()->count());
    }

    public function test_a_comment_report_uses_the_resolver(): void
    {
        $this->app->bind(ReportTargetResolver::class, fn () => new class($this->author->id) implements ReportTargetResolver
        {
            public function __construct(private readonly int $authorId) {}

            public function find(string $type, string $id): ReportTarget
            {
                return new ReportTarget($type, $id, $this->authorId, 'spammy', true);
            }
        });

        $this->actingAs($this->reporter, 'member')
            ->postJson('/reports', $this->body('64b7f0c2e4b0a1a2b3c4d5e6', ['target_type' => 'comment', 'reason' => 'spam']))
            ->assertCreated();

        $this->assertDatabaseHas('reports', ['target_type' => 'comment', 'target_id' => '64b7f0c2e4b0a1a2b3c4d5e6']);
    }

    public function test_guest_is_rejected_but_an_unverified_member_may_report(): void
    {
        $message = $this->message();

        $this->postJson('/reports', $this->body((string) $message->id))->assertUnauthorized();
        $this->assertSame(0, Report::query()->count());

        $this->actingAs($this->member('Nobu', false), 'member')
            ->postJson('/reports', $this->body((string) $message->id))
            ->assertCreated();
    }

    public function test_a_banned_member_cannot_report(): void
    {
        $message = $this->message();
        $this->reporter->forceFill(['banned_at' => now()])->save();

        $this->actingAs($this->reporter, 'member')->postJson('/reports', $this->body((string) $message->id))->assertForbidden();
    }

    public function test_invalid_reason_is_422(): void
    {
        $message = $this->message();

        $this->actingAs($this->reporter, 'member')
            ->postJson('/reports', $this->body((string) $message->id, ['reason' => 'because']))
            ->assertStatus(422);
    }

    public function test_note_over_500_chars_is_422(): void
    {
        $message = $this->message();

        $this->actingAs($this->reporter, 'member')
            ->postJson('/reports', $this->body((string) $message->id, ['note' => str_repeat('a', 501)]))
            ->assertStatus(422);
    }

    public function test_reports_are_rate_limited_per_member(): void
    {
        RateLimiter::clear('reports:'.$this->reporter->id);
        $this->actingAs($this->reporter, 'member');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/reports', $this->body((string) $this->message()->id))->assertCreated();
        }

        $this->postJson('/reports', $this->body((string) $this->message()->id))->assertStatus(429);
    }

    public function test_a_failing_target_lookup_is_503_not_500(): void
    {
        $this->app->bind(ReportTargetResolver::class, fn () => new class implements ReportTargetResolver
        {
            public function find(string $type, string $id): ReportTarget
            {
                throw new \RuntimeException('mongo down');
            }
        });

        $this->actingAs($this->reporter, 'member')
            ->postJson('/reports', $this->body('abc', ['target_type' => 'comment']))
            ->assertStatus(503);
    }

    public function test_the_default_mongo_connection_fails_fast_when_the_server_is_unreachable(): void
    {
        $options = config('database.connections.mongodb.options');

        $this->assertLessThanOrEqual(5000, $options['serverSelectionTimeoutMS']);
        $this->assertLessThanOrEqual(5000, $options['connectTimeoutMS']);
    }
}
