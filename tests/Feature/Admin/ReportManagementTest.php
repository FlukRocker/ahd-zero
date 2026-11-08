<?php

namespace Tests\Feature\Admin;

use App\Contracts\ReportTargetResolver;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Member;
use App\Models\Report;
use App\Models\User;
use App\Services\Moderation\CommentModerator;
use App\Services\Moderation\ReportResolver;
use App\Support\ReportTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Member $author;

    private Member $kai;

    private Member $mio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create();
        $this->author = $this->member('Rin');
        $this->kai = $this->member('Kai');
        $this->mio = $this->member('Mio');
    }

    private function member(string $name): Member
    {
        return Member::create(['name' => $name, 'email' => Str::lower($name).'@example.test', 'password' => 'password123!', 'email_verified_at' => now()]);
    }

    private function message(string $body = 'rude'): ChatMessage
    {
        return ChatMessage::create(['member_id' => $this->author->id, 'body' => $body]);
    }

    private function report(Member $by, string $type, string $id, string $reason = 'abuse', ?string $note = null): Report
    {
        return Report::create(['member_id' => $by->id, 'target_type' => $type, 'target_id' => $id, 'reason' => $reason, 'note' => $note, 'status' => 'open']);
    }

    /** Comment targets without Mongo: a resolver and a moderator that never touch it. */
    private function fakeComments(?int $authorId, bool $available = true): object
    {
        $this->app->bind(ReportTargetResolver::class, fn () => new class($authorId, $available) implements ReportTargetResolver
        {
            public function __construct(private readonly ?int $authorId, private readonly bool $available) {}

            public function find(string $type, string $id): ReportTarget
            {
                return new ReportTarget($type, $id, $this->authorId, 'a comment', $this->available);
            }
        });
        $fake = new class extends CommentModerator
        {
            /** @var array<int, array<int, mixed>> */
            public array $calls = [];

            public function __construct()
            {
                parent::__construct(new ReportResolver);
            }

            public function find(string $commentId): Comment
            {
                return new Comment(['body' => 'x']);
            }

            public function delete(Comment $comment, ?int $adminId, string $deletedBy = 'admin'): void
            {
                $this->calls[] = ['delete', $adminId];
            }

            public function deleteAndBan(Comment $comment, ?string $reason, bool $deleteAll, ?int $adminId): void
            {
                $this->calls[] = ['deleteAndBan', $adminId];
            }
        };
        $this->app->instance(CommentModerator::class, $fake);

        return $fake;
    }

    public function test_queue_lists_open_reports_grouped_by_target_newest_first(): void
    {
        $old = $this->message('old');
        $new = $this->message('new');
        $r1 = $this->report($this->kai, 'chat_message', (string) $old->id, 'spam', 'ads');
        $r1->forceFill(['created_at' => Carbon::now()->subHour()])->save();
        $this->report($this->mio, 'chat_message', (string) $old->id, 'abuse');
        $this->report($this->kai, 'chat_message', (string) $new->id);
        Report::query()->where('target_id', (string) $old->id)->update(['created_at' => Carbon::now()->subHour()]);

        $this->actingAs($this->admin)->get('/dashboard/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Reports')
                ->where('filters.status', 'open')
                ->has('groups', 2)
                ->where('groups.0.id', (string) $new->id)
                ->where('groups.0.count', 1)
                ->where('groups.1.id', (string) $old->id)
                ->where('groups.1.count', 2)
                ->where('groups.1.excerpt', 'old')
                ->where('groups.1.author.name', 'Rin')
                ->where('groups.1.author.memberId', $this->author->id)
                ->where('groups.1.available', true)
                ->where('groups.1.notes', ['ads']));
    }

    public function test_status_filter_shows_resolved_reports(): void
    {
        $message = $this->message();
        $this->report($this->kai, 'chat_message', (string) $message->id);
        Report::query()->update(['status' => 'dismissed']);

        $this->actingAs($this->admin)->get('/dashboard/reports')->assertInertia(fn ($p) => $p->has('groups', 0));
        $this->get('/dashboard/reports?status=dismissed')->assertInertia(fn ($p) => $p->has('groups', 1)->where('filters.status', 'dismissed'));
    }

    public function test_delete_action_deletes_the_chat_message_and_closes_all_its_reports(): void
    {
        $message = $this->message();
        $a = $this->report($this->kai, 'chat_message', (string) $message->id);
        $b = $this->report($this->mio, 'chat_message', (string) $message->id);

        $this->actingAs($this->admin)->postJson("/dashboard/reports/chat_message:{$message->id}/delete")->assertOk();

        $this->assertSoftDeleted('chat_messages', ['id' => $message->id]);
        $this->assertSame('actioned', $a->fresh()->status);
        $this->assertSame('actioned', $b->fresh()->status);
    }

    public function test_ban_chat_and_ban_site_resolve_the_reports_and_apply_the_right_ban(): void
    {
        $one = $this->message();
        $two = $this->message();
        $r1 = $this->report($this->kai, 'chat_message', (string) $one->id);
        $r2 = $this->report($this->kai, 'chat_message', (string) $two->id);

        $this->actingAs($this->admin)->postJson("/dashboard/reports/chat_message:{$one->id}/ban-chat", ['reason' => 'rude'])->assertOk();

        $chat = ChatMember::query()->where('member_id', $this->author->id)->firstOrFail();
        $this->assertNotNull($chat->banned_at);
        $this->assertNull($this->author->fresh()->banned_at);
        $this->assertSame('actioned', $r1->fresh()->status);

        $this->postJson("/dashboard/reports/chat_message:{$two->id}/ban-site")->assertOk();

        $this->assertNotNull($this->author->fresh()->banned_at);
        $this->assertSame('actioned', $r2->fresh()->status);
    }

    public function test_ban_with_unknown_author_is_422_but_delete_and_dismiss_work(): void
    {
        $fake = $this->fakeComments(null);
        $a = $this->report($this->kai, 'comment', 'c1');
        $b = $this->report($this->kai, 'comment', 'c2');

        $this->actingAs($this->admin);
        $this->postJson('/dashboard/reports/comment:c1/ban-comment')->assertStatus(422);
        $this->assertSame('open', $a->fresh()->status);

        $this->postJson('/dashboard/reports/comment:c1/delete')->assertOk();
        $this->assertSame([['delete', $this->admin->id]], $fake->calls);

        $this->postJson('/dashboard/reports/comment:c2/dismiss')->assertOk();
        $this->assertSame('dismissed', $b->fresh()->status);
    }

    public function test_ban_comment_goes_through_delete_and_ban_when_the_author_is_known(): void
    {
        $fake = $this->fakeComments($this->author->id);
        $this->report($this->kai, 'comment', 'c1');

        $this->actingAs($this->admin)->postJson('/dashboard/reports/comment:c1/ban-comment', ['reason' => 'spam'])->assertOk();

        $this->assertSame([['deleteAndBan', $this->admin->id]], $fake->calls);
    }

    public function test_ban_actions_reject_the_wrong_item_kind(): void
    {
        $this->fakeComments($this->author->id);
        $message = $this->message();
        $this->report($this->kai, 'chat_message', (string) $message->id);
        $this->report($this->kai, 'comment', 'c1');

        $this->actingAs($this->admin);
        $this->postJson('/dashboard/reports/comment:c1/ban-chat')->assertStatus(422);
        $this->postJson("/dashboard/reports/chat_message:{$message->id}/ban-comment")->assertStatus(422);
    }

    public function test_dismiss_marks_dismissed(): void
    {
        $message = $this->message();
        $r = $this->report($this->kai, 'chat_message', (string) $message->id);

        $this->actingAs($this->admin)->postJson("/dashboard/reports/chat_message:{$message->id}/dismiss")->assertOk();

        $this->assertSame('dismissed', $r->fresh()->status);
        $this->assertNull($message->fresh()->deleted_at);
        $this->assertSame($this->admin->id, (int) $r->fresh()->resolved_by);
    }

    public function test_unavailable_target_only_allows_dismiss(): void
    {
        $message = $this->message();
        $r = $this->report($this->kai, 'chat_message', (string) $message->id);
        $message->delete();

        $this->actingAs($this->admin);
        $this->postJson("/dashboard/reports/chat_message:{$message->id}/delete")->assertStatus(422);
        $this->postJson("/dashboard/reports/chat_message:{$message->id}/ban-chat")->assertStatus(422);
        $this->postJson("/dashboard/reports/chat_message:{$message->id}/ban-site")->assertStatus(422);
        $this->assertSame('open', $r->fresh()->status);

        $this->get('/dashboard/reports')->assertInertia(fn ($p) => $p->where('groups.0.available', false));
        $this->postJson("/dashboard/reports/chat_message:{$message->id}/dismiss")->assertOk();
        $this->assertSame('dismissed', $r->fresh()->status);
    }

    public function test_a_malformed_target_is_404(): void
    {
        $this->actingAs($this->admin)->postJson('/dashboard/reports/nonsense:1/dismiss')->assertNotFound();
    }

    /** Logs in through the real form: actingAs($m, 'member') would make the member guard the default and let `auth` pass. */
    public function test_non_admin_cannot_reach_reports(): void
    {
        $message = $this->message();
        $r = $this->report($this->mio, 'chat_message', (string) $message->id);

        $this->get('/dashboard/reports')->assertRedirect();
        $this->postJson("/dashboard/reports/chat_message:{$message->id}/dismiss")->assertUnauthorized();

        $this->post('/member/login', ['email' => 'kai@example.test', 'password' => 'password123!']);
        $this->assertAuthenticatedAs($this->kai, 'member');

        $this->get('/dashboard/reports')->assertRedirect();
        $this->postJson("/dashboard/reports/chat_message:{$message->id}/dismiss")->assertUnauthorized();
        $this->assertSame('open', $r->fresh()->status);
    }

    public function test_sidebar_badge_counts_open_reports(): void
    {
        $message = $this->message();
        $this->report($this->kai, 'chat_message', (string) $message->id);
        $this->report($this->mio, 'chat_message', (string) $message->id);

        $this->actingAs($this->admin)->get('/dashboard/reports')->assertInertia(fn ($p) => $p->where('openReports', 2));
    }

    public function test_ban_chat_on_a_message_whose_member_row_is_gone_is_422_and_creates_no_wallet(): void
    {
        $ghost = ChatMessage::query()->create(['member_id' => 999999, 'body' => 'orphan']);
        $this->report($this->kai, 'chat_message', (string) $ghost->id);

        $this->actingAs($this->admin)->postJson("/dashboard/reports/chat_message:{$ghost->id}/ban-chat")->assertStatus(422);

        $this->assertDatabaseMissing('chat_members', ['member_id' => 999999]);
    }
}
