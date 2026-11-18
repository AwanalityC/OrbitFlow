<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarScrollPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_scroll_position_persistence_is_loaded_for_admin_roles(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'sidebar-superadmin@example.test');
        $petugas = $this->makeUser('petugas', 'sidebar-petugas@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="admin-nav"', false)
            ->assertSee('js/orbitflow-admin-sidebar-scroll.js?v=1', false)
            ->assertSee('Cadangan &amp; Pemulihan', false);

        $this->actingAs($petugas)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="admin-nav"', false)
            ->assertSee('js/orbitflow-admin-sidebar-scroll.js?v=1', false)
            ->assertDontSee('Cadangan &amp; Pemulihan', false);

        $script = file_get_contents(public_path('js/orbitflow-admin-sidebar-scroll.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('sessionStorage.setItem(STORAGE_KEY', $script);
        $this->assertStringContainsString('sessionStorage.getItem(STORAGE_KEY)', $script);
        $this->assertStringContainsString('nav.scrollTop', $script);
        $this->assertStringContainsString('nav.scrollLeft', $script);
        $this->assertStringContainsString("window.addEventListener('pagehide', save)", $script);
        $this->assertStringContainsString("window.addEventListener('pageshow'", $script);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Sidebar Test',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
