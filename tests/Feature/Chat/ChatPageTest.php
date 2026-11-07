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

        $this->assertStringContainsString('x-text="line.body"', $html);
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

    public function test_a_guest_sees_the_room_read_only(): void
    {
        $response = $this->get('/chat');

        $response->assertOk()->assertSee('Log in to join the conversation');
        $this->assertFalse($this->bootstrap($response->getContent())['canPost']);
        $this->assertNull($this->bootstrap($response->getContent())['me']);
    }

    public function test_an_unverified_member_is_read_only(): void
    {
        $member = $this->member('new@example.test', verified: false);

        $response = $this->actingAs($member, 'member')->get('/chat');

        $response->assertOk()->assertDontSee('Say something');
        $this->assertFalse($this->bootstrap($response->getContent())['canPost']);
    }

    public function test_a_verified_member_gets_the_composer(): void
    {
        $this->actingAs($this->member(), 'member')->get('/chat')
            ->assertOk()
            ->assertSee('Say something');
    }
}
