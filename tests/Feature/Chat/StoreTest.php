<?php

namespace Tests\Feature\Chat;

use App\Enums\CosmeticType;
use App\Models\ChatCosmetic;
use App\Models\ChatMember;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    private ChatCosmetic $cosmetic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);

        $this->cosmetic = ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Sunset',
            'slug' => 'sunset',
            'payload' => ['color' => '#ff8800'],
            'price' => 100,
            'active' => true,
        ]);
    }

    private function fund(int $coins): void
    {
        ChatMember::updateOrCreate(['member_id' => $this->member->id], ['balance' => $coins]);
    }

    /** Review Focus #1: no chat_members row exists yet. */
    public function test_a_first_time_member_with_no_row_can_be_granted_and_buy(): void
    {
        $this->assertDatabaseCount('chat_members', 0);

        $this->app->make(\App\Services\Chat\StoreService::class)
            ->grantCoins($this->member->id, 150, 1, 'welcome');

        $this->actingAs($this->member, 'member')
            ->postJson("/chat/store/{$this->cosmetic->id}/buy")
            ->assertOk();

        $this->assertSame(50, ChatMember::query()->find($this->member->id)->balance);
    }

    public function test_buying_debits_the_balance_and_writes_a_ledger_row(): void
    {
        $this->fund(100);

        $this->actingAs($this->member, 'member')
            ->postJson("/chat/store/{$this->cosmetic->id}/buy")
            ->assertOk();

        $this->assertSame(0, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseHas('chat_transactions', [
            'member_id' => $this->member->id,
            'delta' => -100,
            'balance_after' => 0,
            'reason' => 'purchase',
        ]);
        $this->assertDatabaseHas('chat_cosmetic_member', [
            'member_id' => $this->member->id,
            'cosmetic_id' => $this->cosmetic->id,
            'source' => 'purchase',
        ]);
    }

    public function test_insufficient_funds_is_refused_without_charging(): void
    {
        $this->fund(99);

        $this->actingAs($this->member, 'member')
            ->postJson("/chat/store/{$this->cosmetic->id}/buy")
            ->assertStatus(422);

        $this->assertSame(99, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseCount('chat_cosmetic_member', 0);
        $this->assertDatabaseCount('chat_transactions', 0);
    }

    public function test_an_inactive_cosmetic_cannot_be_bought(): void
    {
        $this->fund(500);
        $this->cosmetic->update(['active' => false]);

        $this->actingAs($this->member, 'member')
            ->postJson("/chat/store/{$this->cosmetic->id}/buy")
            ->assertStatus(422);

        $this->assertSame(500, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseCount('chat_cosmetic_member', 0);
        $this->assertDatabaseCount('chat_transactions', 0);
    }

    public function test_buying_twice_charges_once(): void
    {
        $this->fund(250);

        $this->actingAs($this->member, 'member')->postJson("/chat/store/{$this->cosmetic->id}/buy")->assertOk();
        $this->actingAs($this->member, 'member')->postJson("/chat/store/{$this->cosmetic->id}/buy")->assertStatus(422);

        $this->assertSame(150, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseCount('chat_cosmetic_member', 1);
        $this->assertDatabaseCount('chat_transactions', 1);
    }

    public function test_equipping_clears_the_previous_item_in_the_same_slot(): void
    {
        $gradient = ChatCosmetic::create([
            'type' => CosmeticType::NameGradient,
            'name' => 'Dawn',
            'slug' => 'dawn',
            'payload' => ['from' => '#ff0000', 'to' => '#0000ff', 'angle' => 45],
            'price' => 0,
            'active' => true,
        ]);
        $this->fund(1000);

        $this->actingAs($this->member, 'member')->postJson("/chat/store/{$this->cosmetic->id}/buy");
        $this->actingAs($this->member, 'member')->postJson("/chat/store/{$gradient->id}/buy");

        $this->actingAs($this->member, 'member')->postJson("/chat/cosmetics/{$this->cosmetic->id}/equip")->assertOk();
        $this->actingAs($this->member, 'member')->postJson("/chat/cosmetics/{$gradient->id}/equip")->assertOk();

        // name_color and name_gradient share the `name` slot, so only one wins.
        $this->assertDatabaseHas('chat_cosmetic_member', [
            'cosmetic_id' => $gradient->id, 'equipped' => true,
        ]);
        $this->assertDatabaseHas('chat_cosmetic_member', [
            'cosmetic_id' => $this->cosmetic->id, 'equipped' => false,
        ]);
    }

    public function test_equipping_something_you_do_not_own_is_forbidden(): void
    {
        $this->actingAs($this->member, 'member')
            ->postJson("/chat/cosmetics/{$this->cosmetic->id}/equip")
            ->assertForbidden();
    }

    public function test_an_admin_grant_writes_a_ledger_row_that_sums_to_the_balance(): void
    {
        $service = $this->app->make(\App\Services\Chat\StoreService::class);

        $service->grantCoins($this->member->id, 60, 1, 'event prize');
        $service->grantCoins($this->member->id, 40, 1, 'second prize');

        $balance = ChatMember::query()->find($this->member->id)->balance;

        $this->assertSame(100, $balance);
        $this->assertSame(100, (int) \App\Models\ChatTransaction::query()
            ->where('member_id', $this->member->id)->sum('delta'));
    }

    public function test_a_slotless_item_cannot_be_equipped_and_is_a_422_not_a_403(): void
    {
        $smiley = ChatCosmetic::create([
            'type' => CosmeticType::Smiley,
            'name' => 'Wink',
            'slug' => 'wink',
            'payload' => ['code' => ':wink:'],
            'price' => 0,
            'active' => true,
        ]);
        $this->actingAs($this->member, 'member')->postJson("/chat/store/{$smiley->id}/buy")->assertOk();

        $this->actingAs($this->member, 'member')
            ->postJson("/chat/cosmetics/{$smiley->id}/equip")
            ->assertStatus(422);
    }

    public function test_granting_a_cosmetic_to_a_first_time_member_is_idempotent_and_audited(): void
    {
        $service = $this->app->make(\App\Services\Chat\StoreService::class);

        $service->grantCosmetic($this->member->id, $this->cosmetic, 1);
        $service->grantCosmetic($this->member->id, $this->cosmetic, 1);

        $this->assertDatabaseCount('chat_cosmetic_member', 1);
        $this->assertDatabaseHas('chat_cosmetic_member', ['source' => 'grant']);
        $this->assertDatabaseCount('chat_transactions', 0);
    }

    public function test_store_index_marks_owned_equipped_and_slot_and_hides_pawn_and_background(): void
    {
        $pawn = ChatCosmetic::create(['type' => CosmeticType::Pawn, 'name' => 'Pawn', 'slug' => 'pawn1', 'payload' => ['asset' => 'a'], 'price' => 1, 'active' => true]);
        $bg = ChatCosmetic::create(['type' => CosmeticType::Background, 'name' => 'Bg', 'slug' => 'bg1', 'payload' => ['asset' => 'a'], 'price' => 1, 'active' => true]);
        $other = ChatCosmetic::create(['type' => CosmeticType::Glow, 'name' => 'Glow', 'slug' => 'glow1', 'payload' => ['color' => '#ffffff', 'radius' => 4], 'price' => 5, 'active' => true]);
        \App\Models\ChatCosmeticMember::create(['member_id' => $this->member->id, 'cosmetic_id' => $this->cosmetic->id, 'acquired_at' => now(), 'source' => 'purchase', 'equipped' => true]);
        $this->fund(7);

        $response = $this->actingAs($this->member, 'member')->get('/chat/store')->assertOk();
        $items = collect($response->viewData('items'));

        $this->assertSame(7, $response->viewData('balance'));
        $this->assertFalse($items->contains('id', $pawn->id));
        $this->assertFalse($items->contains('id', $bg->id));
        $sunset = $items->firstWhere('id', $this->cosmetic->id);
        $this->assertSame('name_color', $sunset['type']);
        $this->assertSame('name', $sunset['slot']);
        $this->assertTrue($sunset['owned']);
        $this->assertTrue($sunset['equipped']);
        $this->assertSame(100, $sunset['price']);
        $glow = $items->firstWhere('id', $other->id);
        $this->assertFalse($glow['owned']);
        $this->assertFalse($glow['equipped']);
        $this->assertSame('effect', $glow['slot']);
    }

    public function test_store_preview_style_is_empty_for_a_hostile_payload(): void
    {
        $this->cosmetic->forceFill(['payload' => ['color' => 'red;background:url(//evil)']])->save();

        $items = collect($this->actingAs($this->member, 'member')->get('/chat/store')->assertOk()->viewData('items'));

        $this->assertSame([], $items->firstWhere('id', $this->cosmetic->id)['style']);
    }

    public function test_buy_returns_the_new_balance(): void
    {
        $this->fund(130);

        $this->actingAs($this->member, 'member')
            ->postJson("/chat/store/{$this->cosmetic->id}/buy")
            ->assertOk()
            ->assertExactJson(['ok' => true, 'balance' => 30]);
    }

    public function test_guests_cannot_buy(): void
    {
        $this->postJson("/chat/store/{$this->cosmetic->id}/buy")->assertUnauthorized();
    }

    public function test_the_store_page_renders_for_a_first_time_member(): void
    {
        $this->actingAs($this->member, 'member')
            ->get('/chat/store')
            ->assertOk()
            ->assertSee('Sunset');
    }

    public function test_a_stale_model_is_refused_when_deactivated_after_loading(): void
    {
        $this->fund(500);
        $stale = ChatCosmetic::query()->findOrFail($this->cosmetic->id);
        $this->cosmetic->update(['active' => false]);

        try {
            $this->app->make(\App\Services\Chat\StoreService::class)->buy($this->member->id, $stale);
            $this->fail('Expected StoreException');
        } catch (\App\Exceptions\StoreException) {
        }

        $this->assertSame(500, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseCount('chat_cosmetic_member', 0);
        $this->assertDatabaseCount('chat_transactions', 0);
    }

    public function test_a_stale_model_is_charged_the_fresh_price(): void
    {
        $this->fund(500);
        $stale = ChatCosmetic::query()->findOrFail($this->cosmetic->id);
        $this->cosmetic->update(['price' => 300]);

        $this->app->make(\App\Services\Chat\StoreService::class)->buy($this->member->id, $stale);

        $this->assertSame(200, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseHas('chat_transactions', ['member_id' => $this->member->id, 'delta' => -300]);
    }

    public function test_a_stale_cheap_model_cannot_buy_a_repriced_item_without_funds(): void
    {
        $this->fund(100);
        $stale = ChatCosmetic::query()->findOrFail($this->cosmetic->id);
        $this->cosmetic->update(['price' => 300]);

        try {
            $this->app->make(\App\Services\Chat\StoreService::class)->buy($this->member->id, $stale);
            $this->fail('Expected StoreException');
        } catch (\App\Exceptions\StoreException) {
        }

        $this->assertSame(100, ChatMember::query()->find($this->member->id)->balance);
        $this->assertDatabaseCount('chat_transactions', 0);
    }

    public function test_equipping_does_not_touch_another_members_item_in_the_same_slot(): void
    {
        $other = Member::create([
            'name' => 'Sora',
            'email' => 'sora@example.test',
            'password' => 'password123!',
            'email_verified_at' => now(),
        ]);
        \App\Models\ChatCosmeticMember::create([
            'member_id' => $other->id,
            'cosmetic_id' => $this->cosmetic->id,
            'acquired_at' => now(),
            'source' => 'grant',
            'equipped' => true,
        ]);
        $this->fund(100);

        $this->actingAs($this->member, 'member')->postJson("/chat/store/{$this->cosmetic->id}/buy")->assertOk();
        $this->actingAs($this->member, 'member')->postJson("/chat/cosmetics/{$this->cosmetic->id}/equip")->assertOk();

        $this->assertDatabaseHas('chat_cosmetic_member', [
            'member_id' => $other->id, 'cosmetic_id' => $this->cosmetic->id, 'equipped' => true,
        ]);
    }
}
