<?php

namespace Tests\Feature\Chat;

use Tests\TestCase;

class GuestOnlineListTest extends TestCase
{
    public function test_guest_can_read_the_online_list(): void
    {
        $this->getJson('/chat/online')
            ->assertOk()
            ->assertJsonStructure(['online'])
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_chat_pages_are_noindex_and_robots_disallows_chat(): void
    {
        $this->get('/chat/mini')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $robots = $this->get('/robots.txt')->getContent();
        $this->assertStringContainsString("Disallow: /chat\n", $robots);
        $this->assertGreaterThanOrEqual(2, substr_count($robots, 'Disallow: /chat'));
    }
}
