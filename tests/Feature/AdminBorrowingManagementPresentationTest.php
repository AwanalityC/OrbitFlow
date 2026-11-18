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

class AdminBorrowingManagementPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrowing_register_has_quick_status_filters_and_keeps_live_search(): void
    {
        $admin = $this->makeUser('admin', 'admin-borrowing-filters@example.test');

        $response = $this->actingAs($admin)->get(route('admin.borrowings.index', [
            'status' => 'pending',
            'q' => 'Kamera',
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]));

        $response->assertOk()
            ->assertSee('Filter cepat status peminjaman')
            ->assertSee('Menunggu')
            ->assertSee('Terlambat')
            ->assertSee('Dikembalikan')
            ->assertSee('Ditolak')
            ->assertSee('data-live-filter', false)
            ->assertSee('data-live-target="#admin-borrowing-results"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('status=active', false)
            ->assertSee('q=Kamera', false);
    }

    public function test_pending_borrowing_review_explains_decision_and_previews_handover_photo(): void
    {
        $admin = $this->makeUser('admin', 'admin-borrowing-review@example.test');
        $student = $this->makeUser('siswa', 'student-borrowing-review@example.test');
        $equipment = Equipment::create([
            'name' => 'Kamera Review',
            'category' => 'Multimedia',
            'description' => 'Kamera untuk kebutuhan dokumentasi.',
        ]);
        $borrowing = $this->makeBorrowing($student, 'Pending');
        $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.borrowing.review', $borrowing))
            ->assertOk()
            ->assertSee('Menunggu pemeriksaan')
            ->assertSee('Periksa alasan, periode, dan ketersediaan unit')
            ->assertSee('js-handover-photo', false)
            ->assertSee('handover-photo-preview', false)
            ->assertSee('required', false)
            ->assertSee('Alasan penolakan');
    }

    public function test_return_form_has_responsive_unit_cards_and_live_selected_unit_count(): void
    {
        Storage::fake('public');

        $admin = $this->makeUser('admin', 'admin-return-presentation@example.test');
        $student = $this->makeUser('siswa', 'student-return-presentation@example.test');
        $equipment = Equipment::create([
            'name' => 'Tripod Pengujian',
            'category' => 'Multimedia',
            'description' => 'Tripod untuk uji tampilan pengembalian.',
        ]);
        $unit = EquipmentUnit::create([
            'equipment_id' => $equipment->id,
            'unit_code' => 'TRIPOD-UI-001',
            'condition' => 'Baik',
            'status' => 'Tersedia',
        ]);
        $borrowing = $this->makeBorrowing($student, 'Pending');
        $borrowing->items()->create([
            'equipment_id' => $equipment->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.borrowing.approve', $borrowing), [
                'handover_photos' => [
                    $equipment->id => UploadedFile::fake()->image('handover.jpg'),
                ],
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.borrowing.return.form', $borrowing))
            ->assertOk()
            ->assertSee('id="return-selected-count"', false)
            ->assertSee('data-label="Pilih unit"', false)
            ->assertSee('data-label="Foto saat diserahkan"', false)
            ->assertSee('data-label="Kondisi saat kembali"', false)
            ->assertSee('data-label="Foto wajib saat kembali"', false)
            ->assertSee('return-mobile-hint', false)
            ->assertSee('TRIPOD-UI-001')
            ->assertSee('JPG/PNG/WEBP');
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Borrowing Test',
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeBorrowing(User $student, string $status): Borrowing
    {
        return Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(3)->toDateString(),
            'purpose' => 'Pengujian tampilan pengelolaan peminjaman.',
            'status' => $status,
        ]);
    }
}
