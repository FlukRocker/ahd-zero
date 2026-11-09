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
        $html = $this->get('/studios')->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-bubble', $html);
        $this->assertStringContainsString('x-data="chatMini"', $html);
        // The panel carries a way out to the full lobby.
        $this->assertStringContainsString('href="'.route('chat.show', [], false).'"', $html);
    }

    public function test_the_homepage_embeds_the_chat_instead_of_the_bubble(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-chat-inline', $html);
        $this->assertStringContainsString('x-data="chatMini"', $html);
        $this->assertStringNotContainsString('data-chat-bubble', $html);
        $this->assertStringNotContainsString('x-html', $html);
    }

    /**
     * The composer is mounted inside <template x-if>, and Alpine only renders a
     * template's FIRST root element. A second top-level element (the rules gate
     * once sat beside the form) silently drops the message box.
     */
    public function test_the_composer_renders_as_a_single_root_element(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render('<x-chat.composer compact />');

        $doc = new \DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
        $body = $doc->getElementsByTagName('body')->item(0);
        $roots = array_filter(iterator_to_array($body->childNodes), fn ($n) => $n instanceof \DOMElement);

        $this->assertCount(1, $roots);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('acceptRules()', $html);
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
