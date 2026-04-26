<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    /**
     * Public admin registration is disabled — admins are invite-only.
     * End-user signup lives on the `member` guard at /member/register
     * and is covered by Tests\Feature\MemberAuthTest.
     */
    public function test_admin_register_route_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
