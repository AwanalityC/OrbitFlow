<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLossLiveFilterPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_loss_filter_returns_only_the_results_fragment(): void
    {
        $staff = User::create([
            'name' => 'Petugas Filter',
            'email' => 'petugas-filter@example.test',
            'password' => 'password123',
            'role' => 'petugas',
            'is_active' => true,
        ]);

        $student = User::create([
            'name' => 'Siswa Filter',
            'email' => 'siswa-filter@example.test',
            'password' => 'password123',
            'role' => 'siswa',
            'is_active' => true,
        ]);

        $equipment = Equipment::create([
            'name' => 'Kamera Uji Filter',
            'category' => 'Multimedia',
        ]);
        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'LOSS-PARTIAL-001',
            'condition' => 'Baik',
            'status' => 'Dipinjam',
        ]);
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => 'Uji live filter kehilangan.',
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

        Loss::create([
            'borrowing_id' => $borrowing->id,
            'equipment_unit_id' => $unit->id,
            'description' => 'Unit untuk menguji respons filter langsung.',
            'status' => 'Dilaporkan',
            'reported_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('admin.losses.index', ['q' => 'LOSS-PARTIAL-001']), [
                'X-OrbitFlow-Live-Filter' => 'partial',
            ])
            ->assertOk()
            ->assertSee('LOSS-PARTIAL-001')
            ->assertSee('Unit untuk menguji respons filter langsung.')
            ->assertDontSee('Laporan Kehilangan Alat')
            ->assertDontSee('admin-filter-form', false)
            ->assertDontSee('<!DOCTYPE html>', false);
    }
}
