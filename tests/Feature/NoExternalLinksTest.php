<?php

namespace Tests\Feature;

use App\Rules\NoExternalLinks;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The allowlist is easy to leave stale after a domain move, and the failure is
 * silent from the developer's side: users simply cannot link to our own site.
 */
class NoExternalLinksTest extends TestCase
{
    private function fails(string $body): bool
    {
        return Validator::make(
            ['body' => $body],
            ['body' => [new NoExternalLinks]],
        )->fails();
    }

    public function test_links_to_the_current_domain_are_allowed(): void
    {
        $this->assertFalse($this->fails('ดูที่ https://animehdzero.net/anime/6947 นะ'));
    }

    public function test_subdomains_of_the_current_domain_are_allowed(): void
    {
        $this->assertFalse($this->fails('https://www.animehdzero.net/anime/1'));
    }

    public function test_links_to_the_previous_domain_still_pass(): void
    {
        // Existing comments predate the move; editing one must not fail.
        $this->assertFalse($this->fails('https://anime-hdzero.com/watch/123'));
    }

    public function test_internal_image_cdns_are_allowed(): void
    {
        $this->assertFalse($this->fails('https://img-cdn.shirokami.me/a/b.webp'));
    }

    public function test_external_links_are_still_rejected(): void
    {
        $this->assertTrue($this->fails('เข้าเว็บนี้ https://spammy.tld/promo'));
    }

    public function test_a_lookalike_domain_is_not_treated_as_ours(): void
    {
        // Suffix matching must not let evil-animehdzero.net through.
        $this->assertTrue($this->fails('https://evil-animehdzero.net/x'));
    }

    public function test_plain_text_without_links_passes(): void
    {
        $this->assertFalse($this->fails('สนุกมากครับ'));
    }
}
