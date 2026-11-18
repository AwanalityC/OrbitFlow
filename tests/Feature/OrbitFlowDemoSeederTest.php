<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Compensation;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Models\User;
use Database\Seeders\OrbitFlowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrbitFlowDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_the_expected_accounts_and_inventory(): void
    {
        $this->seed(OrbitFlowDemoSeeder::class);

        $this->assertDatabaseCount('users', 15);
        $this->assertDatabaseCount('equipment', 12);
        $this->assertDatabaseCount('equipment_units', 24);
        $this->assertDatabaseCount('borrowings', 10);
        $this->assertDatabaseCount('borrowing_items', 15);
        $this->assertDatabaseCount('return_records', 2);
        $this->assertDatabaseCount('losses', 3);
        $this->assertDatabaseCount('compensations', 2);

        $this->assertSame(1, User::where('role', User::ROLE_SUPER_ADMIN)->count());
        $this->assertSame(2, User::where('role', User::ROLE_PETUGAS)->count());
        $this->assertSame(12, User::where('role', User::ROLE_SISWA)->count());

        $admin = User::where('email', 'zoink@orbitflow.test')->firstOrFail();
        $this->assertTrue(Hash::check('OrbitDemo@Admin26!', $admin->password));
    }

    public function test_demo_borrowing_and_loss_data_preserve_physical_unit_consistency(): void
    {
        $this->seed(OrbitFlowDemoSeeder::class);

        $this->assertSame(3, Borrowing::where('status', 'Pending')->count());
        $this->assertSame(2, Borrowing::where('status', 'Borrowed')->count());
        $this->assertSame(1, Borrowing::where('status', 'Overdue')->count());
        $this->assertSame(1, Borrowing::where('status', 'Returned')->count());
        $this->assertSame(1, Borrowing::where('status', 'Rejected')->count());
        $this->assertSame(2, Borrowing::where('status', 'CompletedWithLoss')->count());

        $pendingLoss = Loss::where('status', 'Dilaporkan')->firstOrFail();
        $pendingAllocationIsActive = DB::table('borrowing_item_equipment_unit')
            ->join('borrowing_items', 'borrowing_items.id', '=', 'borrowing_item_equipment_unit.borrowing_item_id')
            ->where('borrowing_items.borrowing_id', $pendingLoss->borrowing_id)
            ->where('borrowing_item_equipment_unit.equipment_unit_id', $pendingLoss->equipment_unit_id)
            ->whereNull('borrowing_item_equipment_unit.returned_at')
            ->where('borrowing_item_equipment_unit.active_equipment_unit_id', $pendingLoss->equipment_unit_id)
            ->exists();

        $this->assertTrue($pendingAllocationIsActive);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $pendingLoss->equipment_unit_id,
            'status' => 'Dipinjam',
            'condition' => 'Baik',
        ]);

        $confirmedLosses = Loss::where('status', 'Dikonfirmasi')->get();
        $this->assertCount(2, $confirmedLosses);

        foreach ($confirmedLosses as $loss) {
            $this->assertDatabaseHas('equipment_units', [
                'id' => $loss->equipment_unit_id,
                'status' => 'Hilang',
                'condition' => 'Hilang',
            ]);
            $this->assertDatabaseHas('borrowings', [
                'id' => $loss->borrowing_id,
                'status' => 'CompletedWithLoss',
            ]);
            $this->assertDatabaseHas('compensations', [
                'loss_id' => $loss->id,
                'status' => 'Pending',
                'amount' => null,
            ]);
        }

        $this->assertSame(0, DB::table('borrowing_item_equipment_unit')
            ->whereNotNull('active_equipment_unit_id')
            ->select('active_equipment_unit_id')
            ->groupBy('active_equipment_unit_id')
            ->havingRaw('COUNT(*) > 1')
            ->count());

        $this->assertSame(5, EquipmentUnit::where('status', 'Dipinjam')->count());
        $this->assertSame(2, EquipmentUnit::where('status', 'Hilang')->count());
    }
}
