<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase5Stage1EnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_public_homepage_without_private_school_data(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kelola alat sekolah dengan cara yang')
            ->assertSee(route('login'), false)
            ->assertDontSee('admin@orbitflow.test');
    }

    public function test_authenticated_user_is_redirected_from_public_homepage_to_dashboard_router(): void
    {
        $student = $this->makeUser('siswa', 'landing-student@example.test');

        $this->actingAs($student)
            ->get(route('home'))
            ->assertRedirect('/dashboard');
    }

    public function test_student_can_report_an_active_borrowed_unit_with_private_evidence(): void
    {
        Storage::fake('local');
        $student = $this->makeUser('siswa', 'loss-owner@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($student, 'LOSS-REPORT-001');

        $this->actingAs($student)
            ->post(route('borrowing.losses.store', [$borrowing, $unit]), [
                'description' => 'Unit tidak ditemukan setelah kegiatan sekolah selesai.',
                'evidence_photo' => UploadedFile::fake()->image('bukti-lokasi.jpg'),
            ])
            ->assertRedirect(route('borrowing.status', ['id' => $borrowing->id]))
            ->assertSessionHasNoErrors();

        $loss = Loss::where('borrowing_id', $borrowing->id)->firstOrFail();
        $this->assertSame('Dilaporkan', $loss->status);
        $this->assertNotEmpty($loss->evidence_photo_path);
        Storage::disk('local')->assertExists($loss->evidence_photo_path);
        $this->assertSame('Dipinjam', $unit->fresh()->status, 'A report must not mark a unit lost before staff verification.');
        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => $unit->id,
            'returned_at' => null,
        ]);
    }

    public function test_student_cannot_report_a_unit_from_another_students_borrowing(): void
    {
        $student = $this->makeUser('siswa', 'loss-outsider@example.test');
        $owner = $this->makeUser('siswa', 'loss-real-owner@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($owner, 'LOSS-REPORT-002');

        $this->actingAs($student)
            ->post(route('borrowing.losses.store', [$borrowing, $unit]), [
                'description' => 'Unit ini hilang dan saya ingin melaporkannya.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('losses', 0);
    }

    public function test_petugas_can_view_the_loss_review_queue(): void
    {
        $petugas = $this->makeUser('petugas', 'loss-queue-petugas@example.test');
        $student = $this->makeUser('siswa', 'loss-queue-student@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($student, 'LOSS-QUEUE-001');
        Loss::create([
            'borrowing_id' => $borrowing->id,
            'equipment_unit_id' => $unit->id,
            'description' => 'Unit tidak ditemukan setelah kegiatan praktik.',
            'status' => 'Dilaporkan',
            'reported_at' => now(),
        ]);

        $this->actingAs($petugas)
            ->get(route('admin.losses.index'))
            ->assertOk()
            ->assertSee('Laporan Kehilangan Alat')
            ->assertSee('LOSS-QUEUE-001')
            ->assertSee('Dilaporkan');
    }

    public function test_admin_confirmation_marks_unit_lost_closes_active_allocation_and_creates_follow_up_record(): void
    {
        $admin = $this->makeUser('petugas', 'loss-reviewer@example.test');
        $student = $this->makeUser('siswa', 'loss-borrower@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($student, 'LOSS-CONFIRM-001');
        $loss = Loss::create([
            'borrowing_id' => $borrowing->id,
            'equipment_unit_id' => $unit->id,
            'description' => 'Unit tidak ditemukan setelah kegiatan.',
            'status' => 'Dilaporkan',
            'reported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.losses.confirm', $loss), [
                'review_notes' => 'Pemeriksaan petugas mengonfirmasi kehilangan.',
            ])
            ->assertRedirect(route('admin.losses.index'));

        $this->assertDatabaseHas('losses', [
            'id' => $loss->id,
            'status' => 'Dikonfirmasi',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $unit->id,
            'condition' => 'Hilang',
            'status' => 'Hilang',
        ]);
        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => null,
            'returned_at' => null,
        ]);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'CompletedWithLoss',
        ]);
        $this->assertDatabaseHas('compensations', [
            'user_id' => $student->id,
            'loss_id' => $loss->id,
            'type' => 'Kehilangan Alat',
            'status' => 'Pending',
        ]);
    }

    public function test_admin_rejection_keeps_unit_borrowed_and_requires_a_reason(): void
    {
        $admin = $this->makeUser('super_admin', 'loss-reject-admin@example.test');
        $student = $this->makeUser('siswa', 'loss-reject-student@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($student, 'LOSS-REJECT-001');
        $loss = Loss::create([
            'borrowing_id' => $borrowing->id,
            'equipment_unit_id' => $unit->id,
            'description' => 'Unit dilaporkan tidak terlihat.',
            'status' => 'Dilaporkan',
            'reported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.losses.index'))
            ->post(route('admin.losses.reject', $loss), ['review_notes' => 'Tidak cukup informasi.'])
            ->assertRedirect(route('admin.losses.index'));

        $this->assertDatabaseHas('losses', ['id' => $loss->id, 'status' => 'Ditolak']);
        $this->assertDatabaseHas('equipment_units', ['id' => $unit->id, 'status' => 'Dipinjam']);
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'Borrowed']);
        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => $unit->id,
            'returned_at' => null,
        ]);
    }

    public function test_pending_loss_report_blocks_return_of_the_same_unit_until_reviewed(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('petugas', 'loss-pending-admin@example.test');
        $student = $this->makeUser('siswa', 'loss-pending-student@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($student, 'LOSS-PENDING-001');
        $loss = Loss::create([
            'borrowing_id' => $borrowing->id,
            'equipment_unit_id' => $unit->id,
            'description' => 'Sedang diperiksa petugas.',
            'status' => 'Dilaporkan',
            'reported_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.borrowing.return.form', $borrowing))
            ->post(route('admin.borrowing.return.store', $borrowing), [
                'returned_units' => [$unit->id],
                'conditions' => [$unit->id => 'Normal'],
                'photos' => [$unit->id => UploadedFile::fake()->image('return.jpg')],
            ])
            ->assertRedirect(route('admin.borrowing.return.form', $borrowing))
            ->assertSessionHasErrors('returned_units');

        $this->assertDatabaseHas('losses', ['id' => $loss->id, 'status' => 'Dilaporkan']);
        $this->assertDatabaseCount('return_records', 0);
    }

    public function test_student_cannot_open_private_loss_evidence_or_admin_loss_review_pages(): void
    {
        $student = $this->makeUser('siswa', 'loss-evidence-outsider@example.test');
        $admin = $this->makeUser('petugas', 'loss-evidence-owner@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($admin, 'LOSS-PRIVATE-001');
        $loss = Loss::create([
            'borrowing_id' => $borrowing->id,
            'equipment_unit_id' => $unit->id,
            'description' => 'Laporan pengujian akses bukti privat.',
            'status' => 'Dilaporkan',
            'reported_at' => now(),
            'evidence_photo_path' => 'loss-evidence/private-test.jpg',
        ]);

        $this->actingAs($student)
            ->get(route('admin.losses.index'))
            ->assertForbidden();

        $this->get(route('admin.losses.evidence', $loss))
            ->assertForbidden();

        $this->post(route('admin.losses.confirm', $loss))
            ->assertForbidden();
    }

    public function test_camera_capture_and_preview_controls_are_available_on_equipment_and_handover_forms(): void
    {
        $admin = $this->makeUser('super_admin', 'camera-admin@example.test');
        $student = $this->makeUser('siswa', 'camera-student@example.test');
        $equipment = Equipment::create(['name' => 'Kamera Kamera', 'category' => 'Multimedia']);
        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'CAMERA-UI-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'purpose' => 'Pengujian formulir kamera.',
            'status' => 'Pending',
        ]);
        $borrowing->items()->create(['equipment_id' => $equipment->id, 'quantity' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.equipment-units.index'))
            ->assertOk()
            ->assertSee('data-camera-capture', false)
            ->assertSee('capture="environment"', false)
            ->assertSee('orbitflow-camera-capture.js?v=1', false);

        $this->get(route('admin.borrowing.review', $borrowing))
            ->assertOk()
            ->assertSee('data-camera-capture', false)
            ->assertSee('handover-preview-' . $equipment->id, false);

        [$activeBorrowing, $activeUnit] = $this->makeActiveBorrowing($student, 'CAMERA-UI-002');
        $this->get(route('admin.borrowing.return.form', $activeBorrowing))
            ->assertOk()
            ->assertSee('data-camera-capture', false)
            ->assertSee('return-photo-preview-' . $activeUnit->id, false);

        $this->actingAs($student)
            ->get(route('borrowing.status', ['id' => $activeBorrowing->id]))
            ->assertOk()
            ->assertSee('data-camera-capture', false)
            ->assertSee('loss-evidence-preview-' . $activeUnit->id, false);
    }

    public function test_student_can_report_two_units_from_one_borrowing_separately_after_first_is_confirmed(): void
    {
        $student = $this->makeUser('siswa', 'loss-two-units@example.test');
        $admin = $this->makeUser('petugas', 'loss-two-units-reviewer@example.test');
        [$borrowing, $firstUnit] = $this->makeActiveBorrowing($student, 'LOSS-TWO-001');

        $secondEquipment = Equipment::create([
            'name' => 'Alat LOSS-TWO-002',
            'category' => 'Multimedia',
        ]);
        $secondUnit = EquipmentUnit::create([
            'equipment_id' => $secondEquipment->id,
            'unit_code' => 'LOSS-TWO-002',
            'condition' => 'Baik',
            'status' => 'Dipinjam',
        ]);
        $secondItem = $borrowing->items()->create([
            'equipment_id' => $secondEquipment->id,
            'quantity' => 1,
        ]);
        DB::table('borrowing_item_equipment_unit')->insert([
            'borrowing_item_id' => $secondItem->id,
            'equipment_unit_id' => $secondUnit->id,
            'active_equipment_unit_id' => $secondUnit->id,
            'borrowed_at' => now(),
            'returned_at' => null,
            'condition_at_borrowing' => 'Baik',
            'handover_photo_path' => null,
            'handover_photo_taken_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('borrowing.losses.store', [$borrowing, $firstUnit]), [
                'description' => 'Unit pertama tidak ditemukan setelah kegiatan.',
            ])
            ->assertRedirect(route('borrowing.status', ['id' => $borrowing->id]))
            ->assertSessionHasNoErrors();

        $firstLoss = Loss::where('equipment_unit_id', $firstUnit->id)->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.losses.confirm', $firstLoss))
            ->assertRedirect(route('admin.losses.index'));

        $this->assertSame('Borrowed', $borrowing->fresh()->status);
        $this->assertSame($secondUnit->id, DB::table('borrowing_item_equipment_unit')
            ->where('borrowing_item_id', $secondItem->id)
            ->value('active_equipment_unit_id'));

        $this->actingAs($student)
            ->post(route('borrowing.losses.store', [$borrowing, $secondUnit]), [
                'description' => 'Unit kedua tidak ditemukan setelah kegiatan.',
            ])
            ->assertRedirect(route('borrowing.status', ['id' => $borrowing->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('losses', 2);
        $secondLoss = Loss::where('equipment_unit_id', $secondUnit->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.losses.confirm', $secondLoss))
            ->assertRedirect(route('admin.losses.index'));

        $this->assertSame('CompletedWithLoss', $borrowing->fresh()->status);
        $this->assertDatabaseHas('equipment_units', ['id' => $firstUnit->id, 'status' => 'Hilang']);
        $this->assertDatabaseHas('equipment_units', ['id' => $secondUnit->id, 'status' => 'Hilang']);
    }

    public function test_student_sees_loss_report_validation_errors_after_redirecting_back(): void
    {
        $student = $this->makeUser('siswa', 'loss-validation-visible@example.test');
        [$borrowing, $unit] = $this->makeActiveBorrowing($student, 'LOSS-VALIDATION-001');

        $this->actingAs($student)
            ->from(route('borrowing.status', ['id' => $borrowing->id]))
            ->post(route('borrowing.losses.store', [$borrowing, $unit]), [
                'description' => 'pendek',
            ])
            ->assertRedirect(route('borrowing.status', ['id' => $borrowing->id]))
            ->assertSessionHasErrors('description');

        $this->get(route('borrowing.status', ['id' => $borrowing->id]))
            ->assertOk()
            ->assertSee('Laporan belum berhasil dikirim.')
            ->assertSee('Keterangan kehilangan minimal 10 karakter.');

        $this->assertDatabaseCount('losses', 0);
    }

    public function test_admin_loss_page_uses_the_shared_live_filter_for_search_and_status(): void
    {
        $admin = $this->makeUser('petugas', 'loss-live-filter@example.test');

        $this->actingAs($admin)
            ->get(route('admin.losses.index'))
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-target="#admin-loss-results"', false)
            ->assertSee('id="admin-loss-results"', false)
            ->assertSee('data-live-results', false)
            ->assertSee('data-live-reset', false)
            ->assertSee('js-live-filter-fallback', false);
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

    /** @return array{0: Borrowing, 1: EquipmentUnit} */
    private function makeActiveBorrowing(User $student, string $unitCode): array
    {
        $equipment = Equipment::create([
            'name' => 'Alat ' . $unitCode,
            'category' => 'Multimedia',
        ]);
        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => $unitCode,
            'condition' => 'Baik',
            'status' => 'Dipinjam',
        ]);
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => 'Pengujian laporan kehilangan alat.',
            'status' => 'Borrowed',
        ]);
        $item = $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        DB::table('borrowing_item_equipment_unit')->insert([
            'borrowing_item_id' => $item->id,
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => $unit->id,
            'borrowed_at' => now(),
            'returned_at' => null,
            'condition_at_borrowing' => 'Baik',
            'handover_photo_path' => null,
            'handover_photo_taken_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$borrowing, $unit];
    }
}
