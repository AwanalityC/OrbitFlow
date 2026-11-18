<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UxChecklistCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_uses_the_shared_student_layout_and_preserves_live_filters(): void
    {
        $student = $this->makeUser('siswa', 'ux-student@example.test');

        $this->actingAs($student)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertSee('Logo OrbitFlow')
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-target="#katalog"', false)
            ->assertSee('id="katalog"', false)
            ->assertSee('orbitflow-ui.css?v=10', false)
            ->assertSee('orbitflow-brand.css?v=3', false)
            ->assertSee('orbitflow-live-filters.js?v=1', false);

        $dashboardSource = file_get_contents(resource_path('views/siswa/dashboard.blade.php'));
        $this->assertIsString($dashboardSource);
        $this->assertStringContainsString("@extends('layouts.siswa')", $dashboardSource);
        $this->assertStringNotContainsString('<html', strtolower($dashboardSource));
    }

    public function test_primary_student_and_admin_pages_use_their_shared_role_layouts(): void
    {
        $studentViews = [
            'siswa/dashboard.blade.php',
            'siswa/equipment-index.blade.php',
            'siswa/equipment-detail.blade.php',
            'siswa/borrowing-form.blade.php',
            'siswa/borrowing-success.blade.php',
            'siswa/borrowing-status.blade.php',
            'siswa/borrowing-history.blade.php',
        ];

        foreach ($studentViews as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertIsString($source, "Could not read {$view}.");
            $this->assertStringContainsString("@extends('layouts.siswa')", $source, "{$view} must use the shared student layout.");
        }

        $adminViews = [
            'admin/dashboard.blade.php',
            'admin/borrowings.blade.php',
            'admin/borrowing-review.blade.php',
            'admin/borrowing-return.blade.php',
            'admin/equipment-units.blade.php',
            'admin/students.blade.php',
            'admin/student-form.blade.php',
            'admin/student-import.blade.php',
            'admin/student-import-preview.blade.php',
            'admin/student-import-result.blade.php',
            'admin/staff.blade.php',
            'admin/staff-form.blade.php',
            'admin/activity-logs.blade.php',
            'admin/activity-log-detail.blade.php',
            'admin/backups/index.blade.php',
        ];

        foreach ($adminViews as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertIsString($source, "Could not read {$view}.");
            $this->assertStringContainsString("@extends('layouts.admin')", $source, "{$view} must use the shared admin layout.");
        }
    }

    public function test_admin_and_student_layouts_reference_the_same_shared_design_assets(): void
    {
        $adminLayout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $studentLayout = file_get_contents(resource_path('views/layouts/siswa.blade.php'));

        $this->assertIsString($adminLayout);
        $this->assertIsString($studentLayout);

        $this->assertStringContainsString("asset('css/orbitflow-ui.css') }}?v=6", $adminLayout);
        $this->assertStringContainsString("asset('css/orbitflow-ui.css') }}?v=10", $studentLayout);
        foreach ([$adminLayout, $studentLayout] as $layout) {
            $this->assertStringContainsString("asset('css/orbitflow-brand.css') }}?v=3", $layout);
            $this->assertStringContainsString("asset('images/orbitflow-mark.svg')", $layout);
        }

        $this->assertStringContainsString("asset('css/orbitflow-admin.css') }}?v=8", $adminLayout);
    }

    public function test_high_impact_admin_actions_show_a_confirmation_before_submission(): void
    {
        $confirmationViews = [
            'admin/borrowing-review.blade.php',
            'admin/student-import-preview.blade.php',
            'admin/partials/equipment-unit-results.blade.php',
            'admin/students.blade.php',
            'admin/staff.blade.php',
            'admin/backups/index.blade.php',
        ];

        foreach ($confirmationViews as $view) {
            $source = file_get_contents(resource_path('views/' . $view));
            $this->assertIsString($source, "Could not read {$view}.");
            $this->assertStringContainsString('confirm(', $source, "{$view} is missing an important-action confirmation.");
        }

        $returnView = file_get_contents(resource_path('views/admin/borrowing-return.blade.php'));
        $this->assertIsString($returnView);
        $this->assertStringContainsString('window.confirm(', $returnView);
        $this->assertStringContainsString('.js-return-unit-check:checked', $returnView);
        $this->assertStringContainsString('Pilih minimal satu unit', $returnView);
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' UX Test',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
