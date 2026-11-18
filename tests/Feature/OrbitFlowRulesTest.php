<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrbitFlowRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_uses_normal_units_before_minor_damage_and_minor_units_remain_available(): void
    {
        Storage::fake('public');
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();

        $minor = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'UNIT-MINOR-001',
            'condition' => 'Rusak Ringan',
            'status' => 'Tersedia',
        ]);
        $normal = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'UNIT-NORMAL-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $firstBorrowing = $this->makeBorrowing($student, $equipment);
        $firstItem = $firstBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $firstBorrowing), [
                'handover_photos' => [$equipment->id => UploadedFile::fake()->image('handover-first.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $firstItem->id,
            'equipment_unit_id' => $normal->id,
            'condition_at_borrowing' => 'Baik',
        ]);
        $this->assertDatabaseMissing('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $firstItem->id,
            'equipment_unit_id' => $minor->id,
        ]);

        // With the normal unit now borrowed, a second approval may use the minor-damage unit.
        $secondBorrowing = $this->makeBorrowing($student, $equipment);
        $secondItem = $secondBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $secondBorrowing), [
                'handover_photos' => [$equipment->id => UploadedFile::fake()->image('handover-second.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $secondItem->id,
            'equipment_unit_id' => $minor->id,
            'condition_at_borrowing' => 'Rusak Ringan',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.return.store', $secondBorrowing), [
                'returned_units' => [$minor->id],
                'conditions' => [$minor->id => 'Rusak Ringan'],
                'notes' => [$minor->id => 'Goresan sudah tercatat sebelum peminjaman.'],
                'photos' => [$minor->id => UploadedFile::fake()->image('return-minor.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('equipment_units', [
            'id' => $minor->id,
            'condition' => 'Rusak Ringan',
            'status' => 'Tersedia',
        ]);
        $this->assertDatabaseMissing('compensations', [
            'user_id' => $student->id,
            'type' => 'Kerusakan Ringan',
        ]);
    }

    public function test_rejection_requires_a_reason_and_student_can_see_it(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();
        $borrowing = $this->makeBorrowing($student, $equipment);
        $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.reject', $borrowing), [])
            ->assertSessionHasErrors('rejection_reason');

        $reason = 'Jumlah unit tidak mencukupi untuk tanggal yang dipilih.';

        $this->actingAs($admin)
            ->post(route('admin.borrowing.reject', $borrowing), ['rejection_reason' => $reason])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'Rejected',
            'rejection_reason' => $reason,
        ]);

        $this->actingAs($student)
            ->get(route('borrowing.status', ['id' => $borrowing->id]))
            ->assertOk()
            ->assertSee($reason);

        $this->actingAs($student)
            ->get(route('borrowing.history'))
            ->assertOk()
            ->assertSee($reason);
    }

    public function test_admin_can_restore_a_repaired_unit_but_cannot_change_a_borrowed_unit(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();

        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'UNIT-REPAIR-001',
            'condition' => 'Rusak Berat',
            'status' => 'Tidak Tersedia',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.equipment-units.update-condition', $unit), ['condition' => 'Baik'])
            ->assertRedirect(route('admin.equipment-units.index'));

        $this->assertDatabaseHas('equipment_units', [
            'id' => $unit->id,
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $unit->update(['status' => 'Dipinjam']);

        $this->actingAs($admin)
            ->post(route('admin.equipment-units.update-condition', $unit), ['condition' => 'Baik'])
            ->assertSessionHasErrors('condition');

        $this->assertDatabaseHas('equipment_units', [
            'id' => $unit->id,
            'status' => 'Dipinjam',
        ]);
    }

    /** @return array{User, User, Equipment} */
    private function makeActorsAndEquipment(): array
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => uniqid('admin-', true) . '@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $student = User::create([
            'name' => 'Siswa Test',
            'email' => uniqid('student-', true) . '@example.test',
            'password' => 'password123',
            'role' => 'siswa',
        ]);

        $equipment = Equipment::create([
            'name' => 'Kamera Pengujian',
            'category' => 'Kamera',
            'description' => 'Unit untuk pengujian aturan OrbitFlow.',
        ]);

        return [$admin, $student, $equipment];
    }

    private function makeBorrowing(User $student, Equipment $equipment): Borrowing
    {
        return Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => 'Pengujian alur peminjaman.',
            'status' => 'Pending',
        ]);
    }
}
