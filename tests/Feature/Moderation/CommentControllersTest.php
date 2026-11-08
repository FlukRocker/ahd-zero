<?php

namespace Tests\Feature\Moderation;

use App\Models\Comment;
use App\Models\Member;
use App\Models\User;
use App\Services\Moderation\CommentModerator;
use App\Services\Moderation\ReportResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The comment collection lives in Mongo, which the test run does not have, so
 * the controllers reach it only through CommentModerator and these tests swap
 * that for a recording fake that hands back an unsaved Comment.
 */
class CommentControllersTest extends TestCase
{
    use RefreshDatabase;

    private function fake(Comment $comment): object
    {
        $fake = new class($comment) extends CommentModerator
        {
            /** @var array<int, array<string, mixed>> */
            public array $calls = [];

            public function __construct(private readonly Comment $comment)
            {
                parent::__construct(new ReportResolver);
            }

            public function find(string $commentId): Comment
            {
                $this->calls[] = ['find', $commentId];

                return $this->comment;
            }

            public function delete(Comment $comment, ?int $adminId, string $deletedBy = 'admin'): void
            {
                $this->calls[] = ['delete', $adminId, $deletedBy];
            }

            public function deleteAndBan(Comment $comment, ?string $reason, bool $deleteAll, ?int $adminId): void
            {
                $this->calls[] = ['deleteAndBan', $reason, $deleteAll, $adminId];
            }
        };
        $this->app->instance(CommentModerator::class, $fake);

        return $fake;
    }

    private function member(): Member
    {
        return Member::create(['name' => 'Rin', 'email' => 'rin@example.test', 'password' => 'password123!', 'email_verified_at' => now()]);
    }

    public function test_admin_destroy_delegates_and_keeps_its_response(): void
    {
        $admin = User::factory()->create();
        $fake = $this->fake(new Comment(['body' => 'x']));

        $this->actingAs($admin)
            ->deleteJson('/dashboard/comments/abc')
            ->assertOk()
            ->assertExactJson(['message' => 'Deleted']);

        $this->assertSame([['find', 'abc'], ['delete', $admin->id, 'admin']], $fake->calls);
    }

    public function test_admin_destroy_and_ban_delegates_and_keeps_its_response(): void
    {
        $admin = User::factory()->create();
        $fake = $this->fake(new Comment(['body' => 'x']));

        $this->actingAs($admin)
            ->postJson('/dashboard/comments/abc/delete-and-ban', ['ban_reason' => 'spam', 'delete_all' => true])
            ->assertOk()
            ->assertExactJson(['message' => 'Deleted and banned']);

        $this->assertSame([['find', 'abc'], ['deleteAndBan', 'spam', true, $admin->id]], $fake->calls);
    }

    public function test_author_delete_goes_through_the_moderator_as_author(): void
    {
        $member = $this->member();
        $fake = $this->fake(new Comment(['body' => 'x', 'user_id' => $member->uuid]));

        $this->actingAs($member, 'member')->deleteJson('/api/comments/abc')->assertOk();

        $this->assertSame([['find', 'abc'], ['delete', null, 'author']], $fake->calls);
    }

    public function test_a_stranger_cannot_delete_someone_elses_comment(): void
    {
        $member = $this->member();
        $fake = $this->fake(new Comment(['body' => 'x', 'user_id' => (string) Str::uuid()]));

        $this->actingAs($member, 'member')->deleteJson('/api/comments/abc')->assertForbidden();

        $this->assertSame([['find', 'abc']], $fake->calls);
    }
}
