<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Compensation;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompensationResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_manual_payment_amount_and_mark_it_paid_after_verification(): void
    {
        [$admin, $loss, $unit, $compensation] = $this->makeConfirmedLoss('payment');

        $this->actingAs($admin)
            ->post(route('admin.losses.compensation', $loss), [
                'resolution_type' => 'Bayar Kompensasi',
                'amount' => '125000.50',
                'resolution_notes' => 'Nominal ditetapkan sesuai keputusan sekolah.',
            ])
            ->assertRedirect(route('admin.losses.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('losses', [
            'id' => $loss->id,
            'status' => 'Hilang Permanen',
        ]);
        $this->assertDatabaseHas('compensations', [
            'id' => $compensation->id,
            'status' => 'Menunggu Pembayaran',
            'resolution_type' => 'Bayar Kompensasi',
            'amount' => '125000.50',
            'decided_by' => $admin->id,
            'resolved_at' => null,
        ]);
        $this->assertSame('Hilang', $unit->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.losses.compensation.complete', $loss), [
                'completion_notes' => 'Pembayaran telah diterima dan diverifikasi admin.',
            ])
            ->assertRedirect(route('admin.losses.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('compensations', [
            'id' => $compensation->id,
            'status' => 'Selesai',
            'resolved_by' => $admin->id,
            'completion_notes' => 'Pembayaran telah diterima dan diverifikasi admin.',
        ]);
        $this->assertSame('Hilang Permanen', $loss->fresh()->status);
    }

    public function test_admin_must_enter_a_manual_amount_for_payment_compensation(): void
    {
        [$admin, $loss, , $compensation] = $this->makeConfirmedLoss('payment-validation');

        $this->actingAs($admin)
            ->from(route('admin.losses.index'))
            ->post(route('admin.losses.compensation', $loss), [
                'resolution_type' => 'Bayar Kompensasi',
                'resolution_notes' => 'Nominal akan ditentukan sekolah.',
            ])
            ->assertRedirect(route('admin.losses.index'))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('compensations', [
            'id' => $compensation->id,
            'status' => 'Pending',
            'resolution_type' => null,
            'amount' => null,
        ]);
    }

    public function test_admin_can_record_a_replacement_unit_in_inventory_before_closing_the_case(): void
    {
        [$admin, $loss, $lostUnit, $compensation] = $this->makeConfirmedLoss('replacement');

        $this->actingAs($admin)
            ->post(route('admin.losses.compensation', $loss), [
                'resolution_type' => 'Ganti Barang',
                'resolution_notes' => 'Siswa menyanggupi mengganti unit dengan barang sejenis.',
            ])
            ->assertRedirect(route('admin.losses.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('compensations', [
            'id' => $compensation->id,
            'status' => 'Menunggu Penggantian',
            'resolution_type' => 'Ganti Barang',
        ]);
        $this->assertSame('Hilang Permanen', $loss->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.losses.compensation.complete', $loss), [
                'replacement_unit_code' => 'UNIT-PENGGANTI-001',
                'replacement_condition' => 'Baik',
                'completion_notes' => 'Unit pengganti sudah diterima dan diperiksa.',
            ])
            ->assertRedirect(route('admin.losses.index'))
            ->assertSessionHasNoErrors();

        $replacement = EquipmentUnit::where('unit_code', 'UNIT-PENGGANTI-001')->firstOrFail();
        $this->assertSame($lostUnit->equipment_id, $replacement->equipment_id);
        $this->assertSame('Baik', $replacement->condition);
        $this->assertSame('Tersedia', $replacement->status);

        $this->assertDatabaseHas('compensations', [
            'id' => $compensation->id,
            'status' => 'Selesai',
            'replacement_equipment_unit_id' => $replacement->id,
        ]);
    }

    public function test_admin_can_waive_compensation_with_a_recorded_reason(): void
    {
        [$admin, $loss, $unit, $compensation] = $this->makeConfirmedLoss('waived');

        $this->actingAs($admin)
            ->post(route('admin.losses.compensation', $loss), [
                'resolution_type' => 'Dibebaskan',
                'resolution_notes' => 'Sekolah membebaskan kompensasi berdasarkan hasil rapat.',
            ])
            ->assertRedirect(route('admin.losses.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('compensations', [
            'id' => $compensation->id,
            'status' => 'Selesai',
            'resolution_type' => 'Dibebaskan',
            'resolved_by' => $admin->id,
        ]);
        $this->assertSame('Hilang Permanen', $loss->fresh()->status);
        $this->assertSame('Hilang', $unit->fresh()->status, 'Waiving compensation must not restore a permanently lost unit.');
    }

    public function test_admin_can_record_a_recovered_unit_and_restore_its_inventory_status_from_inspected_condition(): void
    {
        [$admin, $loss, $unit, $compensation] = $this->makeConfirmedLoss('recovered');

        $this->actingAs($admin)
            ->post(route('admin.losses.compensation', $loss), [
                'resolution_type' => 'Barang Ditemukan',
                'recovered_condition' => 'Rusak Ringan',
                'resolution_notes' => 'Unit ditemukan dan diperiksa petugas inventaris.',
            ])
            ->assertRedirect(route('admin.losses.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('losses', [
            'id' => $loss->id,
            'status' => 'DitemukanKembali',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $unit->id,
            'condition' => 'Rusak Ringan',
            'status' => 'Tersedia',
        ]);
        $this->assertDatabaseHas('compensations', [
            'id' => $compensation->id,
            'status' => 'Selesai',
            'resolution_type' => 'Barang Ditemukan',
            'recovered_condition' => 'Rusak Ringan',
            'resolved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => null,
        ]);
        $this->assertNotNull(DB::table('borrowing_item_equipment_unit')
            ->where('equipment_unit_id', $unit->id)
            ->value('returned_at'));
        $this->assertSame('Returned', $loss->borrowing->fresh()->status);
    }

    public function test_students_cannot_manage_loss_compensation(): void
    {
        [$admin, $loss] = $this->makeConfirmedLoss('permission');
        $student = User::create([
            'name' => 'Siswa Permission',
            'email' => 'student-compensation-permission@example.test',
            'password' => 'password123',
            'role' => 'siswa',
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->post(route('admin.losses.compensation', $loss), [
                'resolution_type' => 'Dibebaskan',
                'resolution_notes' => 'Alasan keputusan sekolah.',
            ])
            ->assertForbidden();

        $this->assertSame('Pending', $loss->compensation->status);
    }

    /** @return array{0: User, 1: Loss, 2: EquipmentUnit, 3: Compensation} */
    private function makeConfirmedLoss(string $suffix): array
    {
        $admin = User::create([
            'name' => 'Admin ' . $suffix,
            'email' => 'admin-comp-' . $suffix . '@example.test',
            'password' => 'password123',
            'role' => 'petugas',
            'is_active' => true,
        ]);
        $student = User::create([
            'name' => 'Siswa ' . $suffix,
            'email' => 'student-comp-' . $suffix . '@example.test',
            'password' => 'password123',
            'role' => 'siswa',
            'is_active' => true,
        ]);
        $equipment = Equipment::create([
            'name' => 'Perangkat ' . $suffix,
            'category' => 'Multimedia',
        ]);
        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'LOST-' . strtoupper($suffix),
            'condition' => 'Hilang',
            'status' => 'Hilang',
        ]);
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->subDays(3)->toDateString(),
            'end_date' => today()->subDay()->toDateString(),
            'purpose' => 'Pengujian tindak lanjut kompensasi.',
            'status' => 'CompletedWithLoss',
        ]);
        $item = $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);
        DB::table('borrowing_item_equipment_unit')->insert([
            'borrowing_item_id' => $item->id,
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => null,
            'borrowed_at' => now()->subDays(2),
            'returned_at' => null,
            'condition_at_borrowing' => 'Baik',
            'handover_photo_path' => null,
            'handover_photo_taken_by' => null,
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ]);
        $loss = Loss::create([
            'borrowing_id' => $borrowing->id,
            'equipment_unit_id' => $unit->id,
            'description' => 'Unit belum ditemukan setelah pemeriksaan.',
            'status' => 'Dikonfirmasi',
            'reported_at' => now()->subDay(),
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => 'Laporan dikonfirmasi petugas.',
        ]);
        $compensation = Compensation::create([
            'user_id' => $student->id,
            'loss_id' => $loss->id,
            'type' => 'Kehilangan Alat',
            'description' => 'Tindak lanjut mengikuti kebijakan sekolah.',
            'status' => 'Pending',
        ]);

        return [$admin, $loss, $unit, $compensation];
    }
}
