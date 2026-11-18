<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsiveNavigationPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_layout_moves_navigation_to_a_labeled_top_bar_on_compact_screens(): void
    {
        $student = $this->makeUser('siswa', 'student-top-nav@example.test');

        $this->actingAs($student)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard', false)
            ->assertSee('Daftar Alat', false)
            ->assertSee('Peminjaman', false)
            ->assertSee('Riwayat', false)
            ->assertSee('orbitflow-ui.css?v=10', false);

        $css = file_get_contents(public_path('css/orbitflow-ui.css'));
        $layout = file_get_contents(resource_path('views/layouts/siswa.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($layout);
        $this->assertStringContainsString('@media (max-width: 760px)', $css);
        $this->assertStringContainsString('position: sticky;', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr));', $css);
        $this->assertStringContainsString('.sidebar .nav-text {', $css);
        $this->assertSame(4, substr_count($layout, '<svg viewBox="0 0 24 24"'));
    }

    public function test_student_dashboard_catalog_photos_fit_without_cropping(): void
    {
        $css = file_get_contents(public_path('css/orbitflow-ui.css'));
        $dashboard = file_get_contents(resource_path('views/siswa/dashboard.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($dashboard);
        $this->assertStringContainsString('.equipment-image.has-equipment-photo > img', $css);
        $this->assertStringContainsString('object-fit: contain;', $css);
        $this->assertStringContainsString('has-equipment-photo', $dashboard);
    }

    public function test_admin_layout_uses_accessible_mobile_navigation_drawer(): void
    {
        $admin = $this->makeUser('admin', 'admin-responsive-nav@example.test');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="admin-sidebar"', false)
            ->assertSee('data-admin-nav-toggle', false)
            ->assertSee('aria-controls="admin-sidebar"', false)
            ->assertSee('data-admin-nav-close', false)
            ->assertSee('data-admin-nav-scrim', false)
            ->assertSee('admin-nav-drawer.js?v=1', false)
            ->assertSee('WORKSPACE', false)
            ->assertSee('OPERASIONAL', false)
            ->assertSee('DATA &amp; AKUN', false);

        $css = file_get_contents(public_path('css/orbitflow-admin.css'));
        $script = file_get_contents(public_path('js/orbitflow-admin-nav-drawer.js'));

        $this->assertIsString($css);
        $this->assertIsString($script);
        $this->assertStringContainsString('.admin-sidebar.is-open', $css);
        $this->assertStringContainsString('.admin-nav-scrim:not([hidden])', $css);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("event.key !== 'Tab'", $script);
        $this->assertStringContainsString("admin-nav-enhanced", $script);
        $this->assertStringContainsString('aria-expanded', $script);
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $this->assertIsString($layout);
        $this->assertSame(9, substr_count($layout, '<span class="admin-nav-icon" aria-hidden="true"><svg'));
        $this->assertStringNotContainsString('<span class="admin-nav-icon">▦</span>', $layout);
    }

    public function test_admin_drawer_preserves_role_restricted_system_navigation(): void
    {
        $petugas = $this->makeUser('petugas', 'petugas-responsive-nav@example.test');
        $superAdmin = $this->makeUser('super_admin', 'super-responsive-nav@example.test');

        $this->actingAs($petugas)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-nav-toggle', false)
            ->assertDontSee('Cadangan &amp; Pemulihan', false);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Cadangan &amp; Pemulihan', false);
    }

    public function test_student_workspace_uses_full_viewport_width_after_mobile_sidebar_moves_to_top(): void
    {
        $css = file_get_contents(public_path('css/orbitflow-ui.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('@media (max-width: 760px)', $css);
        $this->assertStringContainsString('.workspace { width: 100%; max-width: 100%; min-width: 0; margin-left: 0; }', $css);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Responsive Navigation Test',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
