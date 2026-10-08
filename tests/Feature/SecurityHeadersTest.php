<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Each directive as its list of source tokens, so a test matches whole
     * sources: the bare `https:` scheme is a different permission from a
     * single `https://host` and a substring check cannot tell them apart.
     *
     * @return array<string, array<int, string>>
     */
    private function directives(): array
    {
        $header = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $out = [];
        foreach (explode(';', $header) as $directive) {
            $parts = preg_split('/\s+/', trim($directive), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($parts !== []) {
                $name = array_shift($parts);
                $out[$name] = $parts;
            }
        }

        return $out;
    }

    public function test_third_party_ad_scripts_are_allowed(): void
    {
        // The ad tags in the layout are loaded from hosts the networks build at
        // runtime, so an allowlist of names cannot cover them. script-src takes
        // the whole https: scheme instead — the same latitude img-src, frame-src
        // and media-src already have.
        $this->assertContains('https:', $this->directives()['script-src'] ?? []);
    }

    public function test_the_rest_of_the_policy_stays_closed(): void
    {
        $directives = $this->directives();

        // Loosening scripts must not quietly loosen these: plugins stay off,
        // <base> cannot be retargeted, forms only post back to us, and a
        // stylesheet still has to come from this origin.
        $this->assertSame(["'none'"], $directives['object-src'] ?? null);
        $this->assertSame(["'self'"], $directives['base-uri'] ?? null);
        $this->assertSame(["'self'"], $directives['form-action'] ?? null);
        $this->assertNotContains('https:', $directives['style-src'] ?? []);
    }
}
