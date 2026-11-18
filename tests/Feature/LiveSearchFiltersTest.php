<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_and_equipment_catalog_have_live_search_filters(): void
    {
        $student = $this->makeUser('siswa', 'live-search-student@example.test');

        $this->actingAs($student)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-target="#katalog"', false)
            ->assertSee('id="katalog"', false)
            ->assertSee('js/orbitflow-live-filters.js?v=1', false)
            ->assertSee('css/orbitflow-ui.css?v=10', false)
            ->assertSee('css/orbitflow-brand.css?v=3', false)
            ->assertSee('class="brand-mark-image"', false)
            ->assertSee('images/orbitflow-mark.svg', false)
            ->assertDontSee('<span class="brand-mark">O</span>', false);

        $this->assertFileExists(public_path('images/orbitflow-mark.svg'));

        $this->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('data-live-target="#student-equipment-results"', false)
            ->assertSee('id="student-equipment-results"', false)
            ->assertSee('js/orbitflow-live-filters.js?v=1', false);
    }

    public function test_student_borrowing_history_keeps_its_instant_local_search_and_status_filter(): void
    {
        $student = $this->makeUser('siswa', 'live-search-history@example.test');

        $this->actingAs($student)
            ->get(route('borrowing.history'))
            ->assertOk()
            ->assertSee('id="history-search"', false)
            ->assertSee('id="history-status"', false)
            ->assertSee("search?.addEventListener('input', applyFilters)", false)
            ->assertSee("status?.addEventListener('change', applyFilters)", false);
    }

    public function test_admin_student_and_staff_directories_have_live_search_filters(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'live-search-superadmin@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('data-live-target="#admin-student-results"', false)
            ->assertSee('id="admin-student-results"', false);

        $this->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('data-live-target="#admin-staff-results"', false)
            ->assertSee('id="admin-staff-results"', false)
            ->assertSee('value="super_admin"', false)
            ->assertSee('value="petugas"', false);
    }

    public function test_borrowing_and_activity_registers_have_live_filters_for_administrative_roles(): void
    {
        $petugas = $this->makeUser('petugas', 'live-search-petugas@example.test');

        $this->actingAs($petugas)
            ->get(route('admin.borrowings.index'))
            ->assertOk()
            ->assertSee('data-live-target="#admin-borrowing-results"', false)
            ->assertSee('id="admin-borrowing-results"', false);

        $this->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('data-live-target="#admin-activity-results"', false)
            ->assertSee('id="admin-activity-results"', false)
            // Petugas sees only their own activity; this filter must not expose the Super Admin actor selector.
            ->assertDontSee('name="actor_id"', false);
    }

    public function test_super_admin_activity_register_keeps_actor_filter_with_live_search(): void
    {
        $superAdmin = $this->makeUser('super_admin', 'live-search-activity-admin@example.test');

        $this->actingAs($superAdmin)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('name="actor_id"', false)
            ->assertSee('data-live-target="#admin-activity-results"', false);
    }

    public function test_live_search_script_uses_debounce_and_optimized_partial_fragment_replacement(): void
    {
        $script = file_get_contents(public_path('js/orbitflow-live-filters.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('window.setTimeout(() => refreshResults(), delay)', $script);
        $this->assertStringContainsString("requestHeaders['X-OrbitFlow-Live-Filter'] = 'partial'", $script);
        $this->assertStringContainsString("form.dataset.livePartial === 'true'", $script);
        $this->assertStringContainsString("new DOMParser().parseFromString(responseHtml, 'text/html')", $script);
        $this->assertStringContainsString('replacementHtml = replacement.innerHTML', $script);
        $this->assertStringContainsString('target.innerHTML = replacementHtml', $script);
        $this->assertStringContainsString("credentials: 'same-origin'", $script);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst(str_replace(['.', '-', '@example.test'], ' ', $email)),
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
