<?php

namespace Tests\Feature\Chat;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use Tests\TestCase;

class ChatBubbleTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_pages_have_a_bubble_that_opens_the_mini_chat(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-bubble', $html);
        $this->assertStringContainsString('x-data="chatMini"', $html);
        // The panel carries a way out to the full lobby.
        $this->assertStringContainsString('href="'.route('chat.show', [], false).'"', $html);
    }

    public function test_mini_endpoint_returns_lobby_state_uncached_for_a_guest(): void
    {
        $response = $this->getJson('/chat/mini')->assertOk()->assertJson([
            'room' => ['slug' => 'lobby'],
            'me' => null,
            'canPost' => false,
            'myRank' => null,
        ]);

        $this->assertIsArray($response->json('history'));
        $this->assertStringNotContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_bubble_carries_the_report_dialog(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('closeReport()', $html);
    }

    public function test_the_bubble_is_hidden_on_the_chat_pages(): void
    {
        $this->assertStringNotContainsString('data-chat-bubble', $this->get('/chat')->assertOk()->getContent());
        $this->assertStringNotContainsString('data-chat-bubble', $this->get('/chat/store')->getContent());
    }
}
