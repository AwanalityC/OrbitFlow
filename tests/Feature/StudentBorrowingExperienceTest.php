<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentBorrowingExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrowing_form_preserves_submission_fields_and_provides_clear_item_guidance(): void
    {
        $student = $this->makeStudent('borrowing-form');

        $this->actingAs($student)
            ->get(route('borrowing.create'))
            ->assertOk()
            ->assertSee('id="student-borrowing-form"', false)
            ->assertSee('name="items[0][equipment_id]"', false)
            ->assertSee('name="items[0][quantity]"', false)
            ->assertSee('id="start_date"', false)
            ->assertSee('id="end_date"', false)
            ->assertSee('name="purpose"', false)
            ->assertSee('maxlength="1000"', false)
            ->assertSee('id="equipment-options-template"', false)
            ->assertSee('data-item-availability', false)
            ->assertSee('function updateEndDateMinimum()', false)
            ->assertSee('Ajukan peminjaman');

        $this->assertStringContainsString('orbitflow-ui.css?v=10', $this->get(route('borrowing.create'))->getContent());
    }

    public function test_borrowing_history_has_mobile_labels_reset_and_no_matching_results_state(): void
    {
        $student = $this->makeStudent('borrowing-history');

        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => 'Praktik kelas',
            'status' => 'Pending',
        ]);

        $this->actingAs($student)
            ->get(route('borrowing.history'))
            ->assertOk()
            ->assertSee('id="history-search"', false)
            ->assertSee('id="history-status"', false)
            ->assertSee('id="history-reset"', false)
            ->assertSee('id="history-no-results"', false)
            ->assertSee('data-label="ID Peminjaman"', false)
            ->assertSee('data-label="Status"', false)
            ->assertSee('OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT))
            ->assertSee("search?.addEventListener('input', applyFilters)", false)
            ->assertSee("status?.addEventListener('change', applyFilters)", false)
            ->assertSee("reset?.addEventListener('click'", false);
    }

    public function test_status_and_success_pages_show_the_request_reference_and_next_step(): void
    {
        $student = $this->makeStudent('borrowing-status');
        $borrowing = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'purpose' => 'Praktik produksi video',
            'status' => 'Pending',
        ]);
        $reference = 'OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT);

        $this->actingAs($student)
            ->get(route('borrowing.status', ['id' => $borrowing->id]))
            ->assertOk()
            ->assertSee($reference)
            ->assertSee('Menunggu pemeriksaan petugas')
            ->assertSee('Pengajuanmu sudah tercatat.')
            ->assertSee('Tanggal pengembalian')
            ->assertSee('Kembali ke riwayat');

        $this->get(route('borrowing.success', $borrowing))
            ->assertOk()
            ->assertSee($reference)
            ->assertSee('Nomor referensi')
            ->assertSee('Langkah berikutnya')
            ->assertSee('menunggu pemeriksaan petugas inventaris');
    }

    private function makeStudent(string $suffix): User
    {
        return User::create([
            'name' => 'Siswa ' . ucfirst($suffix),
            'email' => $suffix . '-' . uniqid() . '@example.test',
            'password' => 'password123',
            'role' => 'siswa',
            'is_active' => true,
        ]);
    }
}
