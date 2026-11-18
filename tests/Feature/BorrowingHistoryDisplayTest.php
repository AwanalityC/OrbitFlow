<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BorrowingHistoryDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_submission_date_and_time_in_wib_and_newest_request_first(): void
    {
        $student = User::create([
            'name' => 'Siswa Riwayat Test',
            'email' => 'siswa-riwayat@example.test',
            'password' => 'password123',
            'role' => 'siswa',
        ]);

        $older = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-06',
            'purpose' => 'Pengajuan yang lebih lama',
            'status' => 'Pending',
        ]);
        $olderTimestamp = Carbon::parse('2026-11-01 04:15:00', 'UTC');
        $older->created_at = $olderTimestamp;
        $older->updated_at = $olderTimestamp;
        $older->save();

        $newer = Borrowing::create([
            'user_id' => $student->id,
            'start_date' => '2026-11-06',
            'end_date' => '2026-11-07',
            'purpose' => 'Pengajuan yang lebih baru',
            'status' => 'Pending',
        ]);
        $newerTimestamp = Carbon::parse('2026-11-03 02:42:00', 'UTC');
        $newer->created_at = $newerTimestamp;
        $newer->updated_at = $newerTimestamp;
        $newer->save();

        $response = $this->actingAs($student)->get(route('borrowing.history'));

        $response->assertOk()
            ->assertSee('Diajukan pada')
            ->assertSee('03-11-2026')
            ->assertSee('09:42 WIB');

        $html = $response->getContent();
        $newerId = 'OF-' . str_pad((string) $newer->id, 5, '0', STR_PAD_LEFT);
        $olderId = 'OF-' . str_pad((string) $older->id, 5, '0', STR_PAD_LEFT);
        $newerPosition = strpos($html, $newerId);
        $olderPosition = strpos($html, $olderId);

        $this->assertNotFalse($newerPosition, 'The newer borrowing should appear in history.');
        $this->assertNotFalse($olderPosition, 'The older borrowing should appear in history.');
        $this->assertLessThan($olderPosition, $newerPosition, 'The newest borrowing should appear before older borrowings.');
    }
}
