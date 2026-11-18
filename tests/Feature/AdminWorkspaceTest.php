<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_operational_workspace(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ringkasan Operasional')
            ->assertSee('Antrean pengajuan')
            ->assertSee('Kesehatan inventaris')
            ->assertSee('Data peminjam');
    }

    public function test_admin_dashboard_metrics_link_to_their_existing_work_queues(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.borrowings.index', ['status' => 'pending']), false)
            ->assertSee(route('admin.borrowings.index', ['status' => 'active']), false)
            ->assertSee(route('admin.borrowings.index', ['status' => 'Overdue']), false)
            ->assertSee(route('admin.equipment-units.index'), false)
            ->assertSee('Total pengajuan tercatat')
            ->assertSee('Siswa terdaftar');
    }

    public function test_dashboard_shows_staff_and_backup_shortcuts_only_to_super_admin(): void
    {
        $petugas = $this->makeUser('petugas');

        $this->actingAs($petugas)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.staff.index'), false)
            ->assertDontSee(route('admin.backups.index'), false);

        $superAdmin = $this->makeUser('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.staff.index'), false)
            ->assertSee(route('admin.backups.index'), false);
    }

    public function test_admin_can_filter_borrowing_register_by_status_and_search(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeUser('siswa');
        $equipment = Equipment::create([
            'name' => 'Kamera Uji Admin',
            'category' => 'Multimedia',
            'description' => 'Alat pengujian.',
        ]);

        $pending = $this->makeBorrowing($student, 'Pending', 'Permintaan dokumentasi acara.');
        $pending->items()->create(['equipment_id' => $equipment->id, 'quantity' => 1]);
        $this->makeBorrowing($student, 'Rejected', 'Permintaan lama yang ditolak.');

        $this->actingAs($admin)
            ->get(route('admin.borrowings.index', ['status' => 'pending', 'q' => 'Kamera Uji Admin']))
            ->assertOk()
            ->assertSee('OF-' . str_pad($pending->id, 5, '0', STR_PAD_LEFT))
            ->assertSee('Kamera Uji Admin')
            ->assertDontSee('Permintaan lama yang ditolak.');
    }

    public function test_admin_can_search_student_directory(): void
    {
        $admin = $this->makeUser('admin');
        $student = $this->makeUser('siswa', 'Nadia Pratama', 'nadia@example.test');
        $this->makeUser('siswa', 'Rafi Contoh', 'rafi@example.test');

        $this->actingAs($admin)
            ->get(route('admin.students.index', ['q' => 'Nadia']))
            ->assertOk()
            ->assertSee($student->email)
            ->assertSee('Nadia Pratama')
            ->assertDontSee('Rafi Contoh');
    }

    public function test_admin_can_register_equipment_with_multiple_physical_units(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->post(route('admin.equipment.store'), [
                'name' => 'Tripod Uji',
                'category' => 'Multimedia',
                'description' => 'Tripod untuk pengujian admin.',
                'initial_unit_codes' => "TRIPOD-TEST-001\nTRIPOD-TEST-002",
            ])
            ->assertRedirect(route('admin.equipment-units.index'));

        $equipment = Equipment::where('name', 'Tripod Uji')->firstOrFail();
        $this->assertDatabaseHas('equipment_units', [
            'equipment_id' => $equipment->id,
            'unit_code' => 'TRIPOD-TEST-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'equipment_id' => $equipment->id,
            'unit_code' => 'TRIPOD-TEST-002',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
    }

    public function test_admin_cannot_add_duplicate_physical_unit_codes(): void
    {
        $admin = $this->makeUser('admin');
        $equipment = Equipment::create([
            'name' => 'Mikrofon Uji',
            'category' => 'Audio',
            'description' => null,
        ]);
        EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'MIC-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.equipment-units.store', $equipment), [
                'additional_unit_codes' => "MIC-002\nMIC-001",
            ])
            ->assertSessionHasErrors('additional_unit_codes');

        $this->assertDatabaseMissing('equipment_units', [
            'equipment_id' => $equipment->id,
            'unit_code' => 'MIC-002',
        ]);
    }

    public function test_admin_can_add_units_to_an_existing_equipment_record(): void
    {
        $admin = $this->makeUser('admin');
        $equipment = Equipment::create([
            'name' => 'Proyektor Uji',
            'category' => 'Multimedia',
            'description' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.equipment-units.store', $equipment), [
                'additional_unit_codes' => "PROJ-ADD-001\nPROJ-ADD-002",
            ])
            ->assertRedirect(route('admin.equipment-units.index'));

        $this->assertDatabaseHas('equipment_units', [
            'equipment_id' => $equipment->id,
            'unit_code' => 'PROJ-ADD-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'equipment_id' => $equipment->id,
            'unit_code' => 'PROJ-ADD-002',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
    }

    public function test_admin_can_filter_inventory_by_condition(): void
    {
        $admin = $this->makeUser('admin');
        $equipment = Equipment::create([
            'name' => 'Webcam Uji',
            'category' => 'Multimedia',
            'description' => null,
        ]);
        EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'WEBCAM-BAIK',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'WEBCAM-MINOR',
            'condition' => 'Rusak Ringan',
            'status' => 'Tersedia',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.equipment-units.index', ['condition' => 'Rusak Ringan']))
            ->assertOk()
            ->assertSee('WEBCAM-MINOR')
            ->assertDontSee('WEBCAM-BAIK');
    }

    public function test_student_cannot_access_admin_workspace(): void
    {
        $student = $this->makeUser('siswa');

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.students.index'))
            ->assertForbidden();
    }

    private function makeUser(string $role, string $name = 'Pengguna Uji', ?string $email = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email ?: uniqid($role . '-', true) . '@example.test',
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    private function makeBorrowing(User $student, string $status, string $purpose): Borrowing
    {
        return Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(3)->toDateString(),
            'purpose' => $purpose,
            'status' => $status,
        ]);
    }
}
