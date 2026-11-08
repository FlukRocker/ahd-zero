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

    public function test_public_pages_have_a_bubble_linking_to_the_lobby(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*data-chat-bubble[^>]*href="'.preg_quote(route('chat.show', [], false), '#').'"|<a[^>]*href="'.preg_quote(route('chat.show', [], false), '#').'"[^>]*data-chat-bubble#',
            $html,
        );
    }

    public function test_the_bubble_is_hidden_on_the_chat_pages(): void
    {
        $this->assertStringNotContainsString('data-chat-bubble', $this->get('/chat')->assertOk()->getContent());
        $this->assertStringNotContainsString('data-chat-bubble', $this->get('/chat/store')->getContent());
    }
}
