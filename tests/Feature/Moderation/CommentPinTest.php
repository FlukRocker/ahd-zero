<?php

namespace Tests\Feature\Moderation;

use App\Models\Comment;
use App\Models\User;
use App\Services\Moderation\CommentModerator;
use App\Services\Moderation\CommentPinner;
use App\Services\Moderation\ReportResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comments live in Mongo (absent in PHPUnit), so the moderator is faked to
 * return an unsaved Comment and the pinner's count/persist seams are faked.
 */
class CommentPinTest extends TestCase
{
    use RefreshDatabase;

    private function wire(Comment $comment, int $pinnedCount = 0): object
    {
        $moderator = new class($comment) extends CommentModerator
        {
            public function __construct(private readonly Comment $comment)
            {
                parent::__construct(new ReportResolver);
            }

            public function find(string $commentId): Comment
            {
                return $this->comment;
            }
        };
        $pinner = new class($pinnedCount) extends CommentPinner
        {
            /** @var array<int, string> */
            public array $writes = [];

            public function __construct(private readonly int $count) {}

            public function pinnedCount(Comment $comment): int
            {
                return $this->count;
            }
        };
        $this->app->instance(CommentModerator::class, $moderator);
        $this->app->instance(CommentPinner::class, $pinner);

        return $pinner;
    }

    private function comment(array $attrs = []): Comment
    {
        return new class(array_merge(['body' => 'hi', 'commentable_type' => 'anime', 'commentable_id' => 1], $attrs)) extends Comment
        {
            public bool $updated = false;

            /** @param array<string, mixed> $attributes @param array<string, mixed> $options */
            public function update(array $attributes = [], array $options = []): bool
            {
                $this->fill($attributes);
                $this->updated = true;

                return true;
            }
        };
    }

    public function test_pin_requires_admin(): void
    {
        $this->wire($this->comment());

        $this->postJson('/dashboard/comments/abc/pin')->assertUnauthorized();
    }

    public function test_admin_can_pin_and_unpin(): void
    {
        $admin = User::factory()->create();
        $c = $this->comment();
        $this->wire($c);

        $this->actingAs($admin)->postJson('/dashboard/comments/abc/pin')->assertOk()->assertJson(['message' => 'Pinned']);
        $this->assertNotNull($c->pinned_at);

        $this->actingAs($admin)->postJson('/dashboard/comments/abc/unpin')->assertOk();
        $this->assertNull($c->pinned_at);
    }

    public function test_fourth_pin_is_refused_with_422(): void
    {
        $c = $this->comment();
        $this->wire($c, CommentPinner::LIMIT);

        $this->actingAs(User::factory()->create())
            ->postJson('/dashboard/comments/abc/pin')
            ->assertStatus(422)
            ->assertJson(['message' => 'pin limit']);
        $this->assertNull($c->pinned_at);
    }

    public function test_replies_and_deleted_comments_cannot_be_pinned(): void
    {
        $admin = User::factory()->create();

        $this->wire($this->comment(['parent_id' => 'p1']));
        $this->actingAs($admin)->postJson('/dashboard/comments/abc/pin')->assertStatus(422);

        $this->wire($this->comment(['deleted_by' => 'admin']));
        $this->actingAs($admin)->postJson('/dashboard/comments/abc/pin')->assertStatus(422);
    }

    public function test_repinning_an_already_pinned_comment_is_ok_even_at_the_limit(): void
    {
        $c = $this->comment(['pinned_at' => now()]);
        $this->wire($c, CommentPinner::LIMIT);

        $this->actingAs(User::factory()->create())->postJson('/dashboard/comments/abc/pin')->assertOk();
    }
}
