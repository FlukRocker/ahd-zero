<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMember;
use App\Models\Member;
use App\Services\Chat\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SiteWidePresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::forget('chat:online');
    }

    private function member(string $name): Member
    {
        return Member::query()->create([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'password' => 'Test-pass-12345!',
        ]);
    }

    public function test_heartbeat_from_any_page_puts_member_on_the_online_list(): void
    {
        $a = $this->member('Aki');
        $b = $this->member('Bo');

        $this->actingAs($a, 'member')->post('/chat/presence')->assertNoContent();
        $this->actingAs($b, 'member')->post('/chat/presence')->assertNoContent();

        $names = array_column(app(PresenceService::class)->online(), 'name');
        sort($names);
        $this->assertSame(['Aki', 'Bo'], $names);
    }

    public function test_online_endpoint_returns_list_and_counts_the_asker(): void
    {
        $a = $this->member('Aki');

        $response = $this->actingAs($a, 'member')->getJson('/chat/online');

        $response->assertOk()->assertJsonPath('online.0.uuid', $a->uuid)->assertJsonPath('online.0.rank', 'member');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_guests_cannot_ping_or_read_the_list(): void
    {
        $this->post('/chat/presence')->assertRedirect();
        $this->getJson('/chat/online')->assertUnauthorized();
    }

    public function test_member_drops_off_after_heartbeats_stop(): void
    {
        $a = $this->member('Aki');
        $this->actingAs($a, 'member')->post('/chat/presence');

        $this->travel(PresenceService::TTL + 5)->seconds();

        $this->assertSame([], app(PresenceService::class)->online());
    }

    public function test_banned_member_is_not_listed_and_does_not_get_listed_by_pinging(): void
    {
        $a = $this->member('Aki');
        $this->actingAs($a, 'member')->post('/chat/presence');
        ChatMember::query()->updateOrCreate(['member_id' => $a->id], ['rank' => 'member', 'banned_at' => now(), 'banned_until' => null]);

        $this->assertSame([], app(PresenceService::class)->online());

        Cache::forget('chat:online');
        $this->actingAs($a, 'member')->post('/chat/presence');
        $this->assertSame([], app(PresenceService::class)->online());
    }

    public function test_list_carries_the_chat_rank(): void
    {
        $a = $this->member('Aki');
        ChatMember::query()->updateOrCreate(['member_id' => $a->id], ['rank' => 'mod']);
        $this->actingAs($a, 'member')->post('/chat/presence');

        $this->assertSame('mod', app(PresenceService::class)->online()[0]['rank']);
    }

    public function test_every_page_for_a_member_emits_the_presence_marker_and_guests_do_not(): void
    {
        $this->get('/studios')->assertOk()->assertDontSee('ahd-presence', false);

        $this->actingAs($this->member('Aki'), 'member')->get('/studios')->assertOk()->assertSee('name="ahd-presence"', false);
    }

    public function test_list_entries_carry_the_equipped_name_style_map(): void
    {
        $a = $this->member('Aki');
        $this->actingAs($a, 'member')->post('/chat/presence');

        $entry = app(PresenceService::class)->online()[0];

        $this->assertArrayHasKey('style', $entry);
        $this->assertIsArray($entry['style']);
    }
}
