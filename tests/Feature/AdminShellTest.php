<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OpenReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function test_open_reports_count_is_zero_when_the_table_is_missing(): void
    {
        Schema::dropIfExists('reports');

        $this->assertSame(0, OpenReports::count());
    }

    public function test_authenticated_admin_pages_share_open_reports(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('openReports', 0));
    }
}
