<?php

namespace Tests\Feature;

use App\Contracts\AdminFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }

    public function test_dashboard_still_renders_when_the_comment_feed_is_down(): void
    {
        $this->app->bind(AdminFeed::class, fn () => new class implements AdminFeed
        {
            public function latestComments(int $limit = 8): ?array
            {
                return null;
            }

            public function recentChat(int $limit = 8): array
            {
                return [];
            }

            public function modLog(int $limit = 8): array
            {
                return [];
            }
        });
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('latestComments', null)
                ->where('recentChat', [])
                ->where('modLog', [])
                ->where('openReports', 0));
    }
}
