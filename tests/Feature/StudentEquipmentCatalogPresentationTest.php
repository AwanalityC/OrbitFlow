<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEquipmentCatalogPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_catalog_displays_photo_availability_and_condition_details_without_losing_live_filters(): void
    {
        $student = $this->makeStudent('catalog-presentation@example.test');
        $equipment = $this->makeEquipment('Kamera Dokumentasi', 'Multimedia', 'Kamera untuk dokumentasi kegiatan sekolah.');

        $this->makeUnit($equipment, 'CATALOG-CAMERA-001', 'Tersedia', 'Baik', 'equipment/units/catalog-camera-baik.jpg');
        $this->makeUnit($equipment, 'CATALOG-CAMERA-002', 'Tersedia', 'Rusak Ringan');

        $this->actingAs($student)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-target="#student-equipment-results"', false)
            ->assertSee('class="equipment-grid student-equipment-grid"', false)
            ->assertSee('class="equipment-card student-equipment-card"', false)
            ->assertSee('Kamera Dokumentasi')
            ->assertSee('Multimedia')
            ->assertSee('Kondisi Baik')
            ->assertSee('Unit tersedia')
            ->assertSee('2<small> / 2</small>', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee('Baik')
            ->assertSee('Rusak ringan')
            ->assertSee('Detail alat')
            ->assertSee(route('borrowing.create', ['equipment_id' => $equipment->id]), false)
            ->assertSee('css/orbitflow-ui.css?v=10', false);
    }

    public function test_equipment_detail_uses_responsive_photo_and_availability_layout(): void
    {
        $student = $this->makeStudent('catalog-detail@example.test');
        $equipment = $this->makeEquipment('Tripod Dokumentasi', 'Multimedia', 'Tripod untuk kamera sekolah.');

        $this->makeUnit($equipment, 'CATALOG-TRIPOD-001', 'Tersedia', 'Baik', 'equipment/units/catalog-tripod-baik.jpg');
        $this->makeUnit($equipment, 'CATALOG-TRIPOD-002', 'Dipinjam', 'Rusak Ringan');

        $this->actingAs($student)
            ->get(route('equipment.show', $equipment->id))
            ->assertOk()
            ->assertSee('class="panel equipment-detail-card student-equipment-detail"', false)
            ->assertSee('class="student-detail-media has-photo"', false)
            ->assertSee('Kondisi foto: Baik')
            ->assertSee('Tripod Dokumentasi')
            ->assertSee('Unit siap dipinjam')
            ->assertSee('1 <small>dari 2 unit</small>', false)
            ->assertSee('Kondisi unit')
            ->assertSee('Baik / Rusak Ringan')
            ->assertSee('Ajukan Peminjaman')
            ->assertSee('css/orbitflow-ui.css?v=10', false);
    }

    public function test_student_catalog_styles_restore_responsive_sidebar_and_prevent_mobile_overflow(): void
    {
        $css = file_get_contents(public_path('css/orbitflow-ui.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('@media (max-width: 900px)', $css);
        $this->assertStringContainsString('.sidebar { width: 140px; }', $css);
        $this->assertStringContainsString('.workspace { margin-left: 140px; min-width: 0; }', $css);
        $this->assertStringContainsString('@media (max-width: 650px)', $css);
        $this->assertStringContainsString('.sidebar { width: 58px; }', $css);
        $this->assertStringContainsString('.workspace { width: calc(100% - 58px); margin-left: 58px; min-width: 0; }', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $css);
        $this->assertStringContainsString('.student-equipment-card { width: 100%; min-width: 0; }', $css);
        $this->assertStringContainsString('flex: 0 0 auto;', $css);
        $this->assertStringContainsString('align-content: start;', $css);
        $this->assertStringContainsString('grid-auto-rows: max-content;', $css);
        $this->assertStringContainsString('gap: 8px;', $css);
    }

    private function makeStudent(string $email): User
    {
        return User::create([
            'name' => 'Siswa Katalog',
            'email' => $email,
            'password' => 'password123',
            'role' => 'siswa',
            'is_active' => true,
        ]);
    }

    private function makeEquipment(string $name, string $category, string $description): Equipment
    {
        return Equipment::create([
            'name' => $name,
            'category' => $category,
            'description' => $description,
        ]);
    }

    private function makeUnit(
        Equipment $equipment,
        string $code,
        string $status,
        string $condition,
        ?string $photoPath = null
    ): EquipmentUnit {
        return EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => $code,
            'status' => $status,
            'condition' => $condition,
            'current_photo_path' => $photoPath,
        ]);
    }
}
