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

class ReturnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_allocates_specific_units_and_return_updates_each_unit_condition(): void
    {
        Storage::fake('public');
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-workflow@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $student = User::create([
            'name' => 'Siswa Test',
            'email' => 'student-workflow@example.test',
            'password' => 'password123',
            'role' => 'siswa',
        ]);

        $equipment = Equipment::create([
            'name' => 'Kamera Canon',
            'category' => 'Kamera',
            'description' => 'Unit untuk pengujian alur peminjaman.',
        ]);

        $unitA = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'CAM-TEST-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $unitB = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'CAM-TEST-002',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => 'Pengujian alur peminjaman dan pengembalian.',
            'status' => 'Pending',
        ]);

        $borrowingItem = $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $borrowing), [
                'handover_photos' => [$equipment->id => UploadedFile::fake()->image('handover.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'Borrowed',
        ]);

        $this->assertDatabaseHas('equipment_units', [
            'id' => $unitA->id,
            'status' => 'Dipinjam',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $unitB->id,
            'status' => 'Dipinjam',
        ]);
        $this->assertDatabaseCount('borrowing_item_equipment_unit', 2);
        $this->assertSame(2, $borrowingItem->units()->count());

        // Return one unit first: the borrowing must stay active while another unit is out.
        $this->actingAs($admin)
            ->post(route('admin.borrowing.return.store', $borrowing), [
                'returned_units' => [$unitA->id],
                'conditions' => [$unitA->id => 'Normal'],
                'notes' => [$unitA->id => ''],
                'photos' => [$unitA->id => UploadedFile::fake()->image('return-a.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'Borrowed',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $unitA->id,
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $unitB->id,
            'status' => 'Dipinjam',
        ]);

        // Return the remaining unit in damaged condition.
        $this->actingAs($admin)
            ->post(route('admin.borrowing.return.store', $borrowing), [
                'returned_units' => [$unitB->id],
                'conditions' => [$unitB->id => 'Rusak Berat'],
                'notes' => [$unitB->id => 'Lensa rusak saat diperiksa.'],
                'photos' => [$unitB->id => UploadedFile::fake()->image('return-b.jpg')],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('equipment_units', [
            'id' => $unitB->id,
            'condition' => 'Rusak Berat',
            'status' => 'Tidak Tersedia',
        ]);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'Returned',
        ]);
        $this->assertDatabaseCount('return_records', 2);
        $this->assertDatabaseHas('compensations', [
            'user_id' => $student->id,
            'type' => 'Kerusakan Berat',
            'status' => 'Pending',
        ]);

        $this->assertDatabaseMissing('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $borrowingItem->id,
            'equipment_unit_id' => $unitA->id,
            'returned_at' => null,
        ]);
    }
}
