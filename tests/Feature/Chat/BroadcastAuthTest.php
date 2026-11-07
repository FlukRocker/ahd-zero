<?php

namespace Tests\Feature\Chat;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-chat.roster',
        ];
    }

    public function test_the_broadcasting_auth_route_is_registered(): void
    {
        $uris = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri());

        $this->assertTrue($uris->contains('broadcasting/auth'));
        $this->assertFalse($uris->contains('broadcasting/broadcasting/auth'));
    }

    public function test_a_guest_is_turned_away(): void
    {
        // 401, not 403: the route carries auth:member, so the guard rejects
        // before any channel callback runs.
        $this->postJson('/broadcasting/auth', $this->payload())
            ->assertUnauthorized();
    }

    public function test_an_admin_only_session_cannot_join(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/broadcasting/auth', $this->payload())
            ->assertUnauthorized();
    }
}
