<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BorrowingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_database_rejects_two_active_allocations_for_the_same_physical_unit(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();
        $unit = $this->makeAvailableUnit($equipment, 'CONCURRENCY-UNIQUE-001');

        $firstBorrowing = $this->makeBorrowing($student, 'First active allocation');
        $firstItem = $firstBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $secondBorrowing = $this->makeBorrowing($student, 'Second active allocation');
        $secondItem = $secondBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->insertActiveAllocation($firstItem->id, $unit->id);

        try {
            $this->insertActiveAllocation($secondItem->id, $unit->id);
            $this->fail('The database accepted a duplicate active allocation for one physical unit.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('active_equipment_unit_id', strtolower($exception->getMessage()));
        }

        $this->assertDatabaseCount('borrowing_item_equipment_unit', 1);
    }

    public function test_approval_skips_a_unit_with_an_existing_active_claim_even_if_its_status_is_stale(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();
        $unit = $this->makeAvailableUnit($equipment, 'CONCURRENCY-STALE-001');

        $existingBorrowing = $this->makeBorrowing($student, 'Existing active loan');
        $existingItem = $existingBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);
        $existingBorrowing->update(['status' => 'Borrowed']);
        $this->insertActiveAllocation($existingItem->id, $unit->id);

        // Simulate an inconsistent status value. The active database claim must
        // still prevent a second borrowing from receiving this physical unit.
        $unit->update(['status' => 'Tersedia']);

        $pendingBorrowing = $this->makeBorrowing($student, 'Competing pending request');
        $pendingItem = $pendingBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.borrowing.approve', $pendingBorrowing), $this->handoverPayload($pendingBorrowing))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('availability');

        $this->assertDatabaseHas('borrowings', [
            'id' => $pendingBorrowing->id,
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $unit->id,
            'status' => 'Tersedia',
        ]);
        $this->assertDatabaseMissing('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $pendingItem->id,
            'equipment_unit_id' => $unit->id,
        ]);
        $this->assertDatabaseCount('borrowing_item_equipment_unit', 1);
    }

    public function test_multi_equipment_approval_rolls_back_all_unit_claims_when_one_item_is_unavailable(): void
    {
        [$admin, $student] = $this->makeAdminAndStudent();

        $firstEquipment = $this->makeEquipment('Equipment Available');
        $secondEquipment = $this->makeEquipment('Equipment Unavailable');
        $firstUnit = $this->makeAvailableUnit($firstEquipment, 'CONCURRENCY-ROLLBACK-001');

        $borrowing = $this->makeBorrowing($student, 'Request with two equipment types');
        $firstItem = $borrowing->items()->create([
            'equipment_id' => $firstEquipment->id,
            'quantity' => 1,
        ]);
        $borrowing->items()->create([
            'equipment_id' => $secondEquipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.borrowing.approve', $borrowing), $this->handoverPayload($borrowing))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('availability');

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'Pending',
        ]);
        $this->assertDatabaseHas('equipment_units', [
            'id' => $firstUnit->id,
            'status' => 'Tersedia',
        ]);
        $this->assertDatabaseMissing('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $firstItem->id,
            'equipment_unit_id' => $firstUnit->id,
        ]);
        $this->assertDatabaseCount('borrowing_item_equipment_unit', 0);
    }

    public function test_return_releases_active_unit_claim_so_another_request_can_borrow_it(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();
        $unit = $this->makeAvailableUnit($equipment, 'CONCURRENCY-REUSE-001');

        $firstBorrowing = $this->makeBorrowing($student, 'First loan');
        $firstItem = $firstBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $firstBorrowing), $this->handoverPayload($firstBorrowing))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $firstItem->id,
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => $unit->id,
            'returned_at' => null,
        ]);

        $this->post(route('admin.borrowing.return.store', $firstBorrowing), [
            'returned_units' => [$unit->id],
            'conditions' => [$unit->id => 'Normal'],
            'notes' => [$unit->id => ''],
            'photos' => [$unit->id => UploadedFile::fake()->image('return-concurrency.jpg')],
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $firstItem->id,
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => null,
        ]);

        $secondBorrowing = $this->makeBorrowing($student, 'Second loan after return');
        $secondItem = $secondBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->post(route('admin.borrowing.approve', $secondBorrowing), $this->handoverPayload($secondBorrowing))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $secondItem->id,
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => $unit->id,
            'returned_at' => null,
        ]);
    }

    public function test_student_availability_views_exclude_units_with_active_claims(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();
        $unit = $this->makeAvailableUnit($equipment, 'CONCURRENCY-VIEW-001');
        $existingBorrowing = $this->makeBorrowing($student, 'Existing active loan for availability view');
        $existingItem = $existingBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);
        $existingBorrowing->update(['status' => 'Borrowed']);
        $this->insertActiveAllocation($existingItem->id, $unit->id);
        $unit->update(['status' => 'Tersedia']);

        $this->actingAs($student)
            ->get(route('borrowing.create'))
            ->assertOk()
            // Availability is attached to each equipment option and displayed
            // by the form after the student selects that equipment.
            ->assertSee('data-available="0"', false)
            ->assertSee('data-good="0"', false)
            ->assertSee('data-minor="0"', false);
    }

    public function test_student_cannot_submit_quantity_based_on_a_unit_with_an_active_claim(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();
        $unit = $this->makeAvailableUnit($equipment, 'CONCURRENCY-SUBMIT-001');
        $existingBorrowing = $this->makeBorrowing($student, 'Existing active loan for submission');
        $existingItem = $existingBorrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);
        $existingBorrowing->update(['status' => 'Borrowed']);
        $this->insertActiveAllocation($existingItem->id, $unit->id);
        $unit->update(['status' => 'Tersedia']);

        $this->actingAs($student)
            ->from(route('borrowing.create'))
            ->post(route('borrowing.store'), [
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDay()->toDateString(),
                'purpose' => 'Pengujian unit yang sudah memiliki alokasi aktif.',
                'items' => [
                    ['equipment_id' => $equipment->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect(route('borrowing.create'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('borrowings', [
            'purpose' => 'Pengujian unit yang sudah memiliki alokasi aktif.',
        ]);
    }

    public function test_an_approved_borrowing_cannot_be_approved_a_second_time(): void
    {
        [$admin, $student, $equipment] = $this->makeActorsAndEquipment();
        $unit = $this->makeAvailableUnit($equipment, 'CONCURRENCY-DOUBLE-001');
        $borrowing = $this->makeBorrowing($student, 'Duplicate approval attempt');
        $item = $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $borrowing), $this->handoverPayload($borrowing))
            ->assertRedirect(route('admin.dashboard'));

        $this->post(route('admin.borrowing.approve', $borrowing), $this->handoverPayload($borrowing))->assertStatus(400);

        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'Borrowed',
        ]);
        $this->assertDatabaseCount('borrowing_item_equipment_unit', 1);
        $this->assertDatabaseHas('borrowing_item_equipment_unit', [
            'borrowing_item_id' => $item->id,
            'equipment_unit_id' => $unit->id,
            'active_equipment_unit_id' => $unit->id,
        ]);
    }

    private function insertActiveAllocation(int $borrowingItemId, int $unitId): void
    {
        DB::table('borrowing_item_equipment_unit')->insert([
            'borrowing_item_id' => $borrowingItemId,
            'equipment_unit_id' => $unitId,
            'active_equipment_unit_id' => $unitId,
            'borrowed_at' => now(),
            'returned_at' => null,
            'condition_at_borrowing' => 'Baik',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function handoverPayload(Borrowing $borrowing): array
    {
        $borrowing->loadMissing('items');
        $photos = [];
        foreach ($borrowing->items as $item) {
            $photos[$item->equipment_id] = UploadedFile::fake()->image('handover-' . $item->equipment_id . '.jpg');
        }

        return ['handover_photos' => $photos];
    }

    private function makeActorsAndEquipment(): array
    {
        [$admin, $student] = $this->makeAdminAndStudent();

        return [$admin, $student, $this->makeEquipment('Kamera Uji Concurrency')];
    }

    private function makeAdminAndStudent(): array
    {
        $admin = User::create([
            'name' => 'Admin Concurrency',
            'email' => uniqid('admin-concurrency-', true) . '@example.test',
            'password' => 'long-test-password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $student = User::create([
            'name' => 'Siswa Concurrency',
            'email' => uniqid('student-concurrency-', true) . '@example.test',
            'password' => 'long-test-password',
            'role' => 'siswa',
            'is_active' => true,
        ]);

        return [$admin, $student];
    }

    private function makeEquipment(string $name): Equipment
    {
        return Equipment::create([
            'name' => $name,
            'category' => 'Multimedia',
            'description' => 'Peralatan untuk pengujian concurrency.',
        ]);
    }

    private function makeAvailableUnit(Equipment $equipment, string $code): EquipmentUnit
    {
        return EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => $code,
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
    }

    private function makeBorrowing(User $student, string $purpose): Borrowing
    {
        return Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => $purpose,
            'status' => 'Pending',
        ]);
    }
}
