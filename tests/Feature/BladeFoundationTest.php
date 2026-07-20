<?php

namespace Tests\Feature;

use Tests\TestCase;

class BladeFoundationTest extends TestCase
{
    public function test_global_composer_shares_site_data_with_blade_views(): void
    {
        $this->withoutVite();

        // Render an inline Blade string through the view factory so the
        // '*' composer runs against it.
        $rendered = view('errors.404')->render();

        $this->assertStringContainsString(config('app.name'), $rendered);
    }
}
