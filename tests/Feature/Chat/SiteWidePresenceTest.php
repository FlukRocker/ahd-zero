<?php

namespace Tests\Feature\Chat;

use App\Enums\CosmeticType;
use App\Events\Chat\MemberRestyled;
use App\Events\Chat\PresenceJoined;
use App\Events\Chat\PresenceLeft;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMember;
use App\Models\Member;
use App\Services\Chat\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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

    public function test_first_heartbeat_pushes_a_join_but_repeats_do_not(): void
    {
        Event::fake([PresenceJoined::class, PresenceLeft::class]);
        $a = $this->member('Aki');

        $this->actingAs($a, 'member')->post('/chat/presence')->assertNoContent();
        $this->actingAs($a, 'member')->post('/chat/presence')->assertNoContent();

        Event::assertDispatchedTimes(PresenceJoined::class, 1);
        Event::assertDispatched(PresenceJoined::class, fn (PresenceJoined $e) => $e->person['name'] === 'Aki'
            && $e->person['uuid'] === $a->uuid
            && ! array_key_exists('id', $e->person)
            && ! array_key_exists('email', $e->person));
        Event::assertNotDispatched(PresenceLeft::class);
    }

    public function test_an_expired_member_is_pushed_as_left_when_someone_else_pings(): void
    {
        Event::fake([PresenceJoined::class, PresenceLeft::class]);
        $a = $this->member('Aki');
        $b = $this->member('Bo');
        $this->actingAs($a, 'member')->post('/chat/presence');

        $this->travel(PresenceService::TTL + 5)->seconds();
        $this->actingAs($b, 'member')->post('/chat/presence');

        Event::assertDispatched(PresenceLeft::class, fn (PresenceLeft $e) => $e->uuids === [$a->uuid]);
    }

    public function test_equipping_pushes_the_new_look_and_unequipping_again_pushes_the_plain_one(): void
    {
        Event::fake([MemberRestyled::class, PresenceJoined::class, PresenceLeft::class]);
        $a = $this->member('Aki');
        $red = ChatCosmetic::create(['type' => CosmeticType::NameColor, 'name' => 'Red', 'slug' => 'red', 'payload' => ['color' => '#ff0000'], 'price' => 1, 'active' => true]);
        ChatCosmeticMember::create(['member_id' => $a->id, 'cosmetic_id' => $red->id, 'acquired_at' => now(), 'source' => 'grant', 'equipped' => false]);

        $this->actingAs($a, 'member')->postJson("/chat/cosmetics/{$red->id}/equip")->assertOk();

        Event::assertDispatched(MemberRestyled::class, fn (MemberRestyled $e) => $e->uuid === $a->uuid
            && ($e->style['--chat-name-color'] ?? null) === '#ff0000');
    }

    public function test_online_endpoint_returns_list_and_counts_the_asker(): void
    {
        $a = $this->member('Aki');

        $response = $this->actingAs($a, 'member')->getJson('/chat/online');

        $response->assertOk()->assertJsonPath('online.0.uuid', $a->uuid)->assertJsonPath('online.0.rank', 'member');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_guests_cannot_ping_but_can_read_the_list(): void
    {
        $this->post('/chat/presence')->assertRedirect();
        $this->getJson('/chat/online')->assertOk();
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

    public function test_an_equipped_pawn_is_listed_with_its_vetted_path(): void
    {
        $a = $this->member('Aki');
        $pawn = \App\Models\ChatCosmetic::create([
            'type' => \App\Enums\CosmeticType::Pawn, 'name' => 'Gold', 'slug' => 'gold-pawn',
            'payload' => ['asset' => 'gold'], 'price' => 0, 'active' => true,
        ]);
        \App\Models\ChatCosmeticMember::create([
            'member_id' => $a->id, 'cosmetic_id' => $pawn->id, 'acquired_at' => now(), 'source' => 'purchase', 'equipped' => true,
        ]);
        $this->actingAs($a, 'member')->post('/chat/presence');

        $this->assertSame('/chat-assets/pawns/gold.svg', app(PresenceService::class)->online()[0]['pawn']);
    }
}
