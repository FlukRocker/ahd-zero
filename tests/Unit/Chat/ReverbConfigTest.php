<?php

namespace Tests\Unit\Chat;

use Tests\TestCase;

class ReverbConfigTest extends TestCase
{
    public function test_client_events_are_off_origins_are_narrowed_and_rate_limiting_is_on(): void
    {
        $app = config('reverb.apps.apps.0');

        $this->assertSame('none', $app['accept_client_events_from']);
        $this->assertNotSame(['*'], $app['allowed_origins']);
        $this->assertNotEmpty($app['allowed_origins']);
        $this->assertTrue($app['rate_limiting']['enabled']);
    }

    public function test_the_broadcast_client_has_a_short_timeout(): void
    {
        $this->assertSame(3, config('broadcasting.connections.reverb.client_options.timeout'));
    }
}
