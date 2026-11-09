<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\MessagePinned;
use App\Events\Chat\MessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Member;
use App\Models\User;
use App\Support\Chat\ChatPins;
use App\Support\Chat\ChatRules;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReplyPinRulesTest extends TestCase
{
    use RefreshDatabase;

    private ?string $settingsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([MessageSent::class, MessagePinned::class]);

        // The settings file is real storage: never leave a test's pins/rules in it.
        $path = storage_path('app/site_settings.json');
        $this->settingsBackup = file_exists($path) ? (string) file_get_contents($path) : null;
        SiteSettings::set('chat_pins', []);
    }

    protected function tearDown(): void
    {
        $path = storage_path('app/site_settings.json');
        $this->settingsBackup === null ? @unlink($path) : file_put_contents($path, $this->settingsBackup);

        parent::tearDown();
    }

    private function member(string $name, string $rank = 'member'): Member
    {
        $m = Member::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'password123!']);

        if ($rank !== 'member') {
            ChatMember::create(['member_id' => $m->id, 'rank' => $rank, 'balance' => 0]);
        }

        return $m;
    }

    private function say(Member $m, array $data)
    {
        return $this->actingAs($m, 'member')->postJson('/chat/messages', $data);
    }

    public function test_a_reply_stores_a_token_and_returns_a_preview_of_the_original(): void
    {
        $rin = $this->member('Rin');
        $aki = $this->member('Aki');
        $first = $this->say($rin, ['body' => 'hello [b]there[/b]'])->assertCreated()->json();

        $reply = $this->say($aki, ['body' => 'hi back', 'reply_to' => $first['id']])->assertCreated()->json();

        $this->assertSame('hi back', $reply['body']);
        $this->assertSame(['id' => $first['id'], 'name' => 'Rin', 'text' => 'hello there'], $reply['reply']);
        $this->assertSame('[re='.$first['id'].'] hi back', ChatMessage::query()->findOrFail($reply['id'])->body);
        $this->assertSame([['t' => 'text', 'v' => 'hi back']], $reply['parts']);
    }

    public function test_the_reply_token_cannot_be_typed_and_the_target_must_exist_in_the_room(): void
    {
        $rin = $this->member('Rin');
        $first = $this->say($rin, ['body' => 'hello'])->assertCreated()->json();
        $other = ChatRoom::create(['slug' => 'other', 'name' => 'Other', 'sort' => 2, 'active' => true]);

        $this->say($rin, ['body' => '[re='.$first['id'].'] sneaky'])->assertStatus(422);
        $this->say($rin, ['body' => 'x', 'reply_to' => 999999])->assertStatus(422);
        $this->say($rin, ['body' => 'x', 'reply_to' => $first['id'], 'room_id' => $other->id])->assertStatus(422);
    }

    public function test_a_reply_to_a_deleted_message_shows_it_as_gone_in_history(): void
    {
        $rin = $this->member('Rin');
        $first = $this->say($rin, ['body' => 'hello'])->assertCreated()->json();
        $this->say($rin, ['body' => 'again', 'reply_to' => $first['id']])->assertCreated();
        ChatMessage::query()->findOrFail($first['id'])->delete();
        app(\App\Contracts\ChatHistory::class)->forget(ChatRoom::lobby()->id);

        $history = $this->get('/chat')->assertOk()->getContent();
        preg_match('#<script type="application/json" id="chat-bootstrap">(.*?)</script>#s', $history, $m);
        $data = json_decode($m[1], true);

        $this->assertSame(['id' => $first['id'], 'name' => null, 'text' => null], $data['history'][0]['reply']);
    }

    public function test_a_mod_can_pin_and_unpin_but_a_member_cannot(): void
    {
        $rin = $this->member('Rin');
        $mod = $this->member('Mod', 'mod');
        $msg = $this->say($rin, ['body' => 'important'])->assertCreated()->json();

        $this->actingAs($rin, 'member')->postJson("/chat/messages/{$msg['id']}/pin")->assertForbidden();
        $this->assertNull(ChatPins::get(ChatRoom::lobby()->id));

        $this->actingAs($mod, 'member')->postJson("/chat/messages/{$msg['id']}/pin")->assertOk();
        $this->assertSame($msg['id'], ChatPins::get(ChatRoom::lobby()->id));
        Event::assertDispatched(MessagePinned::class, fn (MessagePinned $e) => ($e->pinned['id'] ?? null) === $msg['id']);

        $this->actingAs($mod, 'member')->deleteJson("/chat/messages/{$msg['id']}/pin")->assertOk();
        $this->assertNull(ChatPins::get(ChatRoom::lobby()->id));
        $this->assertDatabaseHas('chat_moderation_log', ['action' => 'pin_message', 'actor_member_id' => $mod->id]);
    }

    public function test_the_pinned_message_is_in_the_page_bootstrap_and_cleared_when_deleted(): void
    {
        $rin = $this->member('Rin');
        $mod = $this->member('Mod', 'mod');
        $msg = $this->say($rin, ['body' => 'important'])->assertCreated()->json();
        $this->actingAs($mod, 'member')->postJson("/chat/messages/{$msg['id']}/pin")->assertOk();

        $html = $this->get('/chat')->assertOk()->getContent();
        preg_match('#<script type="application/json" id="chat-bootstrap">(.*?)</script>#s', $html, $m);
        $this->assertSame($msg['id'], json_decode($m[1], true)['pinned']['id']);

        $this->actingAs($rin, 'member')->deleteJson("/chat/messages/{$msg['id']}")->assertOk();
        $this->assertNull(ChatPins::get(ChatRoom::lobby()->id));
    }

    public function test_an_admin_can_pin_from_the_dashboard(): void
    {
        $rin = $this->member('Rin');
        $msg = $this->say($rin, ['body' => 'important'])->assertCreated()->json();
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson("/dashboard/chat/messages/{$msg['id']}/pin")->assertOk();
        $this->assertSame($msg['id'], ChatPins::get(ChatRoom::lobby()->id));
        $this->actingAs($admin)->deleteJson("/dashboard/chat/messages/{$msg['id']}/pin")->assertOk();
        $this->assertNull(ChatPins::get(ChatRoom::lobby()->id));
    }

    public function test_rules_are_served_with_a_version_and_the_admin_can_edit_or_disable_them(): void
    {
        $admin = User::factory()->create();
        $bootstrap = function (): array {
            preg_match('#<script type="application/json" id="chat-bootstrap">(.*?)</script>#s', $this->get('/chat')->getContent(), $m);

            return json_decode($m[1], true);
        };

        $this->actingAs($admin)->postJson('/dashboard/chat/rules', ['rules' => "1. be nice\n2. no spam"])->assertOk();
        $first = $bootstrap()['rules'];
        $this->assertSame("1. be nice\n2. no spam", $first['text']);

        $this->actingAs($admin)->postJson('/dashboard/chat/rules', ['rules' => '1. be kind'])->assertOk();
        $this->assertNotSame($first['version'], $bootstrap()['rules']['version']);

        $this->actingAs($admin)->postJson('/dashboard/chat/rules', ['rules' => ''])->assertOk();
        $this->assertNull($bootstrap()['rules']);
        $this->assertSame(ChatRules::DEFAULT, trim(ChatRules::DEFAULT));
    }

    public function test_members_and_guests_cannot_edit_rules(): void
    {
        $this->postJson('/dashboard/chat/rules', ['rules' => 'x'])->assertUnauthorized();
        $this->actingAs($this->member('Rin'), 'member')->postJson('/dashboard/chat/rules', ['rules' => 'x'])->assertForbidden();
    }

    public function test_pins_in_two_rooms_do_not_disturb_each_other(): void
    {
        $lobby = ChatRoom::lobby()->id;
        $other = ChatRoom::create(['slug' => 'other', 'name' => 'Other', 'sort' => 2, 'active' => true])->id;

        ChatPins::set($lobby, 10);
        ChatPins::set($other, 20);
        ChatPins::set($lobby, 11);

        $this->assertSame(11, ChatPins::get($lobby));
        $this->assertSame(20, ChatPins::get($other));
        $this->assertCount(2, ChatPins::all());

        ChatPins::clear($lobby);
        $this->assertNull(ChatPins::get($lobby));
        $this->assertSame(20, ChatPins::get($other));
    }

    public function test_a_stale_unpin_does_not_clear_a_newer_pin(): void
    {
        $rin = $this->member('Rin');
        $mod = $this->member('Mod', 'mod');
        $old = $this->say($rin, ['body' => 'old'])->assertCreated()->json();
        $new = $this->say($rin, ['body' => 'new'])->assertCreated()->json();
        $this->actingAs($mod, 'member')->postJson("/chat/messages/{$new['id']}/pin")->assertOk();

        $this->actingAs($mod, 'member')->deleteJson("/chat/messages/{$old['id']}/pin")->assertOk();

        $this->assertSame($new['id'], ChatPins::get(ChatRoom::lobby()->id));
    }
}
