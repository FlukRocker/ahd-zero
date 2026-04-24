<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_renders_when_enabled(): void
    {
        config(['site.registration_enabled' => true]);

        $this->get('/member/register')->assertOk();
    }

    public function test_register_page_403_when_disabled_via_env(): void
    {
        config(['site.registration_enabled' => false]);

        $this->get('/member/register')->assertStatus(403);
    }

    public function test_register_post_403_when_disabled_via_env(): void
    {
        config(['site.registration_enabled' => false]);

        $this->post('/member/register', [
            'name' => 'X',
            'email' => 'x@example.test',
            'password' => 'password123!',
            'password_confirmation' => 'password123!',
        ])->assertStatus(403);
    }

    public function test_register_page_403_when_disabled_via_admin_file(): void
    {
        config(['site.registration_enabled' => true]);

        $path = storage_path('app/site_settings.json');
        file_put_contents($path, json_encode(['registration_enabled' => false]));

        try {
            $this->get('/member/register')->assertStatus(403);
        } finally {
            @unlink($path);
        }
    }
}
