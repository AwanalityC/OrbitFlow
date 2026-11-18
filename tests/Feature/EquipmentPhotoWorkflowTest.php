<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\ReturnRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EquipmentPhotoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_equipment_with_a_catalog_photo(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('super_admin', 'photo-register-admin@example.test');
        $photo = UploadedFile::fake()->image('kamera-awal.jpg');

        $this->actingAs($admin)
            ->post(route('admin.equipment.store'), [
                'name' => 'Kamera Foto Uji',
                'category' => 'Multimedia',
                'description' => 'Kamera untuk pengujian foto katalog.',
                'initial_unit_codes' => 'PHOTO-CAM-001',
                'photo' => $photo,
            ])
            ->assertRedirect(route('admin.equipment-units.index'));

        $equipment = Equipment::where('name', 'Kamera Foto Uji')->firstOrFail();
        $this->assertNotEmpty($equipment->image_path);
        Storage::disk('public')->assertExists($equipment->image_path);
    }

    public function test_admin_can_update_a_non_borrowed_units_current_condition_photo(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('petugas', 'photo-unit-admin@example.test');
        $equipment = $this->makeEquipment('Tripod Foto Uji');
        $unit = $this->makeUnit($equipment, 'PHOTO-TRIPOD-001');

        $this->actingAs($admin)
            ->post(route('admin.equipment-units.update-photo', $unit), [
                'photo' => UploadedFile::fake()->image('tripod-current.jpg'),
            ])
            ->assertRedirect(route('admin.equipment-units.index'));

        $unit->refresh();
        $this->assertNotEmpty($unit->current_photo_path);
        Storage::disk('public')->assertExists($unit->current_photo_path);
    }

    public function test_admin_cannot_manually_change_photo_while_a_unit_is_borrowed(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('super_admin', 'photo-borrowed-admin@example.test');
        $student = $this->makeUser('siswa', 'photo-borrowed-student@example.test');
        $equipment = $this->makeEquipment('Kamera Sedang Dipinjam');
        $unit = $this->makeUnit($equipment, 'PHOTO-BORROWED-001', 'Dipinjam');

        $this->actingAs($admin)
            ->from(route('admin.equipment-units.index'))
            ->post(route('admin.equipment-units.update-photo', $unit), [
                'photo' => UploadedFile::fake()->image('must-not-save.jpg'),
            ])
            ->assertRedirect(route('admin.equipment-units.index'))
            ->assertSessionHasErrors('photo');

        $this->assertNull($unit->fresh()->current_photo_path);
    }

    public function test_approval_requires_a_handover_photo_for_each_equipment_type(): void
    {
        $admin = $this->makeUser('super_admin', 'photo-required-admin@example.test');
        $student = $this->makeUser('siswa', 'photo-required-student@example.test');
        $equipment = $this->makeEquipment('Kamera Wajib Foto');
        $this->makeUnit($equipment, 'PHOTO-REQUIRED-001');
        $borrowing = $this->makeBorrowing($student, $equipment);

        $this->actingAs($admin)
            ->from(route('admin.borrowing.review', $borrowing))
            ->post(route('admin.borrowing.approve', $borrowing), [])
            ->assertRedirect(route('admin.borrowing.review', $borrowing))
            ->assertSessionHasErrors('handover_photos.' . $equipment->id);

        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'Pending']);
    }

    public function test_approval_saves_handover_photo_to_each_allocated_unit(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('super_admin', 'photo-handover-admin@example.test');
        $student = $this->makeUser('siswa', 'photo-handover-student@example.test');
        $equipment = $this->makeEquipment('Kamera Handover');
        $unitA = $this->makeUnit($equipment, 'PHOTO-HANDOVER-001');
        $unitB = $this->makeUnit($equipment, 'PHOTO-HANDOVER-002');
        $borrowing = $this->makeBorrowing($student, $equipment, 2);
        $photo = UploadedFile::fake()->image('dua-kamera.jpg');

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $borrowing), [
                'handover_photos' => [$equipment->id => $photo],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $unitA->refresh();
        $unitB->refresh();
        $this->assertNotEmpty($unitA->current_photo_path);
        $this->assertSame($unitA->current_photo_path, $unitB->current_photo_path);
        Storage::disk('public')->assertExists($unitA->current_photo_path);
        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'equipment_unit_id' => $unitA->id,
            'handover_photo_path' => $unitA->current_photo_path,
            'handover_photo_taken_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'equipment_unit_id' => $unitB->id,
            'handover_photo_path' => $unitB->current_photo_path,
        ]);
    }

    public function test_return_requires_a_photo_and_saves_it_as_the_units_current_photo(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('super_admin', 'photo-return-admin@example.test');
        $student = $this->makeUser('siswa', 'photo-return-student@example.test');
        $equipment = $this->makeEquipment('Kamera Pengembalian');
        $unit = $this->makeUnit($equipment, 'PHOTO-RETURN-001');
        $borrowing = $this->makeBorrowing($student, $equipment);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $borrowing), [
                'handover_photos' => [$equipment->id => UploadedFile::fake()->image('handover.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)
            ->from(route('admin.borrowing.return.form', $borrowing))
            ->post(route('admin.borrowing.return.store', $borrowing), [
                'returned_units' => [$unit->id],
                'conditions' => [$unit->id => 'Normal'],
                'notes' => [$unit->id => ''],
            ])
            ->assertRedirect(route('admin.borrowing.return.form', $borrowing))
            ->assertSessionHasErrors('photos.' . $unit->id);

        $returnPhoto = UploadedFile::fake()->image('kamera-setelah-kembali.jpg');
        $this->post(route('admin.borrowing.return.store', $borrowing), [
            'returned_units' => [$unit->id],
            'conditions' => [$unit->id => 'Normal'],
            'notes' => [$unit->id => 'Kondisi normal setelah diperiksa.'],
            'photos' => [$unit->id => $returnPhoto],
        ])->assertRedirect(route('admin.dashboard'));

        $record = ReturnRecord::where('borrowing_id', $borrowing->id)->firstOrFail();
        $unit->refresh();
        $this->assertNotEmpty($record->photo_path);
        $this->assertSame($record->photo_path, $unit->current_photo_path);
        $this->assertSame($admin->id, $record->photo_taken_by);
        Storage::disk('public')->assertExists($record->photo_path);
    }

    public function test_student_catalog_uses_photo_of_the_best_current_unit_condition(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('equipment/baik.jpg', 'good-photo');
        Storage::disk('public')->put('equipment/minor.jpg', 'minor-photo');

        $student = $this->makeUser('siswa', 'catalog-photo-student@example.test');
        $equipment = $this->makeEquipment('Kamera Katalog Foto');
        $good = $this->makeUnit($equipment, 'PHOTO-CATALOG-BAIK');
        $good->update(['current_photo_path' => 'equipment/baik.jpg']);
        $minor = $this->makeUnit($equipment, 'PHOTO-CATALOG-MINOR', 'Tersedia', 'Rusak Ringan');
        $minor->update(['current_photo_path' => 'equipment/minor.jpg']);

        $this->actingAs($student)
            ->get(route('equipment.index'))
            ->assertOk()
            ->assertSee('equipment/baik.jpg')
            ->assertDontSee('equipment/minor.jpg');
    }

    public function test_admin_inventory_page_renders_external_live_filters_with_a_safe_fallback(): void
    {
        $admin = $this->makeUser('super_admin', 'live-filter-ui-admin@example.test');

        $this->actingAs($admin)
            ->get(route('admin.equipment-units.index'))
            ->assertOk()
            ->assertSee('id="inventory-live-filter"', false)
            ->assertSee('id="inventory-live-status"', false)
            ->assertSee('orbitflow-inventory-live.js?v=1', false)
            ->assertSee('class="admin-btn primary js-filter-fallback"', false)
            ->assertSee('#inventory-live-filter.is-live .js-filter-fallback{display:none!important}', false)
            ->assertSee('#inventory-live-filter.is-live.has-error .js-filter-fallback{display:inline-flex!important}', false)
            ->assertSee('orbitflow-admin.css?v=8', false);

        $liveFilterScript = file_get_contents(public_path('js/orbitflow-inventory-live.js'));

        $this->assertIsString($liveFilterScript);
        $this->assertStringContainsString(
            'window.setTimeout(() => refreshResults(), 180)',
            $liveFilterScript
        );
    }

    public function test_admin_inventory_live_filter_can_search_and_filter_by_category(): void
    {
        $admin = $this->makeUser('super_admin', 'live-filter-admin@example.test');
        $multimedia = $this->makeEquipment('Kamera Doogie', 'Multimedia');
        $audio = $this->makeEquipment('Mikrofon Doogie', 'Audio');
        $this->makeUnit($multimedia, 'LIVE-DOOGIE-CAM');
        $this->makeUnit($audio, 'LIVE-DOOGIE-MIC');

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->get(route('admin.equipment-units.index', [
                'q' => 'doo',
                'category' => 'Multimedia',
            ]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonFragment(['count' => 1]);
        $this->assertStringContainsString('LIVE-DOOGIE-CAM', $response->json('html'));
        $this->assertStringNotContainsString('LIVE-DOOGIE-MIC', $response->json('html'));
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

    private function makeEquipment(string $name, string $category = 'Multimedia'): Equipment
    {
        return Equipment::create(['name' => $name, 'category' => $category]);
    }

    private function makeUnit(Equipment $equipment, string $code, string $status = 'Tersedia', string $condition = 'Baik'): EquipmentUnit
    {
        return EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => $code,
            'condition' => $condition,
            'status' => $status,
        ]);
    }

    private function makeBorrowing(User $student, Equipment $equipment, int $quantity = 1): Borrowing
    {
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => 'Pengujian foto kondisi alat.',
            'status' => 'Pending',
        ]);
        $borrowing->items()->create(['equipment_id' => $equipment->id, 'quantity' => $quantity]);

        return $borrowing;
    }
}
