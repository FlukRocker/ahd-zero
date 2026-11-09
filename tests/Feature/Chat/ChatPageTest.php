<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use Tests\TestCase;

class ChatPageTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function member(string $email = 'rin@example.test', bool $verified = true): Member
    {
        $member = Member::create([
            'name' => 'Rin',
            'email' => $email,
            'password' => 'password123!',
        ]);
        $member->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return $member;
    }

    /** Pulls the JSON out of the bootstrap script block. */
    private function bootstrap(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="chat-bootstrap">(.*?)</script>#s', $html, $m));

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_bootstrap_carries_my_rank_and_null_for_guests(): void
    {
        $mod = $this->member('mod@example.test');
        \App\Models\ChatMember::create(['member_id' => $mod->id, 'rank' => \App\Enums\ChatRank::Mod]);
        $plain = $this->member('plain@example.test');

        $this->assertSame('mod', $this->bootstrap($this->actingAs($mod, 'member')->get('/chat')->getContent())['myRank']);
        $this->assertSame('member', $this->bootstrap($this->actingAs($plain, 'member')->get('/chat')->getContent())['myRank']);

        $this->app['auth']->guard('member')->logout();
        $this->assertNull($this->bootstrap($this->get('/chat')->getContent())['myRank']);
    }

    public function test_header_has_no_store_link(): void
    {
        $guest = $this->get('/')->getContent();
        $this->assertStringContainsString('href="'.route('chat.show', [], false).'"', $guest);
        $this->assertStringNotContainsString('href="'.route('chat.store', [], false).'"', $guest);

        $html = $this->actingAs($this->member(), 'member')->get('/')->getContent();
        $this->assertStringContainsString('href="'.route('chat.show', [], false).'"', $html);
        $this->assertStringNotContainsString('href="'.route('chat.store', [], false).'"', $html);
    }

    public function test_chat_page_has_tabs_and_room_sidebar(): void
    {
        $other = \App\Models\ChatRoom::create(['slug' => 'anime', 'name' => 'Anime Talk', 'sort' => 5, 'active' => true]);

        $html = $this->actingAs($this->member(), 'member')->get('/chat')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<a[^>]*href="[^"]*/chat"[^>]*aria-current="page"[^>]*>\s*Chat#', $html);
        $this->assertStringContainsString('href="'.route('chat.store', [], false).'"', $html);
        $this->assertStringContainsString('href="'.route('chat.room', 'anime', false).'"', $html);
        $this->assertStringContainsString('Anime Talk', $html);
        $this->assertStringContainsString('Message #', $html);
        $this->assertStringNotContainsString(':disabled="sending"', $html);

        $guest = $this->app['auth']->guard('member');
        $guest->logout();
        $html = $this->get('/chat')->getContent();
        $this->assertStringNotContainsString('href="'.route('chat.store', [], false).'"', $html);
    }

    public function test_store_page_has_tabs_and_no_sidebar(): void
    {
        $html = $this->actingAs($this->member(), 'member')->get('/chat/store')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<a[^>]*href="[^"]*/chat/store"[^>]*aria-current="page"#', $html);
        $this->assertStringContainsString('href="'.route('chat.show', [], false).'"', $html);
        $this->assertStringNotContainsString('data-chat-sidebar', $html);
        $this->assertStringNotContainsString('Online', $html);
    }

    public function test_bootstrap_contains_room_and_no_member_id(): void
    {
        $member = $this->member();
        ChatMessage::create(['member_id' => $member->id, 'body' => 'hi']);

        $data = $this->bootstrap($this->actingAs($member, 'member')->get('/chat')->getContent());

        $this->assertSame(['id', 'slug', 'name'], array_keys($data['room']));
        $this->assertSame('lobby', $data['room']['slug']);
        $this->assertArrayNotHasKey('member_id', $data['history'][0]);
        $this->assertArrayNotHasKey('member_id', $data);
    }

    public function test_a_hostile_message_body_cannot_break_out_of_the_bootstrap(): void
    {
        $member = $this->member();
        $body = "\"><script>alert(1)</script> & 'x'";
        ChatMessage::create(['member_id' => $member->id, 'body' => $body]);

        $response = $this->get('/chat');

        $response->assertOk();
        $html = $response->getContent();

        // No raw markup, quote, ampersand or apostrophe from the body survives
        // anywhere in the page: the JSON is hex-escaped.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('"><script', $html);
        $this->assertStringContainsString('\\u0022\\u003E\\u003Cscript\\u003Ealert(1)\\u003C\\/script\\u003E', $html);
        $this->assertStringContainsString('\\u0026', $html);
        $this->assertStringContainsString('\\u0027x\\u0027', $html);

        // The payload is only ever inside the script block, never an attribute,
        // and decodes back to the exact literal text.
        $this->assertStringNotContainsString('x-data="{ msgs', $html);
        $data = $this->bootstrap($html);
        $this->assertSame($body, $data['history'][0]['body']);
    }

    public function test_the_client_renders_text_not_html(): void
    {
        $html = $this->get('/chat')->getContent();

        $this->assertStringContainsString('x-text="part.v"', $html);
        $this->assertStringContainsString('x-text="line.name"', $html);
        $this->assertStringNotContainsString('x-html', $html);
        $this->assertStringNotContainsString('x-html', (string) file_get_contents(resource_path('js/chat/lobby.js')));
        $this->assertStringNotContainsString('innerHTML', (string) file_get_contents(resource_path('js/chat/lobby.js')));
    }

    public function test_a_hostile_display_name_is_hex_escaped_too(): void
    {
        $member = $this->member();
        $member->forceFill(['name' => '<img src=x onerror=alert(1)>'])->save();
        ChatMessage::create(['member_id' => $member->id, 'body' => 'hi']);

        $html = $this->get('/chat')->getContent();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertSame('<img src=x onerror=alert(1)>', $this->bootstrap($html)['history'][0]['name']);
    }

    public function test_the_bootstrap_block_is_json_not_an_attribute(): void
    {
        $this->get('/chat')
            ->assertSee('id="chat-bootstrap"', false)
            ->assertSee('type="application/json"', false);
    }

    public function test_the_bootstrap_exposes_uuid_but_never_the_numeric_member_id(): void
    {
        $member = $this->member();
        ChatMessage::create(['member_id' => $member->id, 'body' => 'hello']);

        $html = $this->actingAs($member, 'member')->get('/chat')->getContent();
        $data = $this->bootstrap($html);

        $this->assertSame($member->uuid, $data['me']);
        $this->assertTrue($data['canPost']);
        $this->assertArrayNotHasKey('member_id', $data['history'][0]);
        $this->assertArrayNotHasKey('echo', $data);
    }

    public function test_lobby_markup_has_moderation_hooks_and_no_x_html(): void
    {
        $html = $this->get('/chat')->assertOk()->getContent();

        $this->assertStringContainsString('actionsFor(line)', $html);
        $this->assertStringContainsString('moderate(', $html);
        $this->assertStringNotContainsString('x-html', $html);
        $this->assertStringNotContainsString('innerHTML', $html);
    }

    public function test_a_guest_sees_the_room_read_only(): void
    {
        $response = $this->get('/chat');

        $response->assertOk()->assertSee('Log in to join the conversation');
        $this->assertFalse($this->bootstrap($response->getContent())['canPost']);
        $this->assertNull($this->bootstrap($response->getContent())['me']);
    }

    public function test_an_unverified_member_can_post(): void
    {
        $member = $this->member('new@example.test', verified: false);

        $response = $this->actingAs($member, 'member')->get('/chat');

        $response->assertOk()->assertSee('Message #');
        $this->assertTrue($this->bootstrap($response->getContent())['canPost']);
    }

    /**
     * Each .chat-row is its own stacking context (isolation: isolate), so the
     * menu's z-10 only orders it inside its own row and the next row paints
     * over it. The row holding the open menu must be raised above its siblings.
     * Alpine is not rendered in PHPUnit; the paint order itself is checked in
     * the browser (plan Task 9).
     */
    public function test_the_row_with_the_open_menu_is_raised_above_following_rows(): void
    {
        $html = $this->actingAs($this->member(), 'member')->get('/chat')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<li class="chat-row[^>]*:class="\{ \'z-20\': menuOpen === line\.id \}"/', $html);
    }

    public function test_a_verified_member_gets_the_composer(): void
    {
        $this->actingAs($this->member(), 'member')->get('/chat')
            ->assertOk()
            ->assertSee('Message #');
    }

    public function test_message_menu_has_a_report_entry_for_posters(): void
    {
        $html = $this->actingAs($this->member(), 'member')->get('/chat')->assertOk()->getContent();

        $this->assertStringContainsString('report:', $html);
        $this->assertStringContainsString('รายงาน', $html);
        $this->assertStringContainsString('x-model="reportReason"', $html);
        $this->assertStringNotContainsString('x-html', $html);
    }
}
