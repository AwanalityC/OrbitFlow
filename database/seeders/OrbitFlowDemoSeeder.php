<?php

namespace Database\Seeders;

use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Compensation;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Models\ReturnRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrbitFlowDemoSeeder extends Seeder
{
    /**
     * Seed a coherent, clearly labelled OrbitFlow demo dataset.
     *
     * Run this on a fresh database with `php artisan migrate:fresh --seed`.
     * The seeder intentionally creates no fake image paths or compensation
     * amounts, and all physical-unit allocations follow OrbitFlow's active
     * allocation guard.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $units = $this->seedEquipment();
            $this->seedBorrowings($users, $units);
        });
    }

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        $accounts = [
            'admin' => [
                'name' => 'Zoink (Demo)',
                'email' => 'zoink@orbitflow.test',
                'password' => 'OrbitDemo@Admin26!',
                'role' => User::ROLE_SUPER_ADMIN,
            ],
            'petugas01' => [
                'name' => 'wPopoff (Demo)',
                'email' => 'wpopoff@orbitflow.test',
                'password' => 'OrbitDemo@Staff26!',
                'role' => User::ROLE_PETUGAS,
            ],
            'petugas02' => [
                'name' => 'Trick (Demo)',
                'email' => 'trick@orbitflow.test',
                'password' => 'OrbitDemo@Staff27!',
                'role' => User::ROLE_PETUGAS,
            ],
            'siswa01' => ['name' => 'Netermind (Demo)', 'email' => 'netermind@orbitflow.test', 'password' => 'OrbitDemo@Siswa01!', 'role' => User::ROLE_SISWA],
            'siswa02' => ['name' => 'zLevii (Demo)', 'email' => 'zlevii@orbitflow.test', 'password' => 'OrbitDemo@Siswa02!', 'role' => User::ROLE_SISWA],
            'siswa03' => ['name' => 'Vorten (Demo)', 'email' => 'vorten@orbitflow.test', 'password' => 'OrbitDemo@Siswa03!', 'role' => User::ROLE_SISWA],
            'siswa04' => ['name' => 'SerVax (Demo)', 'email' => 'servax@orbitflow.test', 'password' => 'OrbitDemo@Siswa04!', 'role' => User::ROLE_SISWA],
            'siswa05' => ['name' => 'Nickname (Demo)', 'email' => 'nickname@orbitflow.test', 'password' => 'OrbitDemo@Siswa05!', 'role' => User::ROLE_SISWA],
            'siswa06' => ['name' => 'w3rty (Demo)', 'email' => 'w3rty@orbitflow.test', 'password' => 'OrbitDemo@Siswa06!', 'role' => User::ROLE_SISWA],
            'siswa07' => ['name' => 'kloone (Demo)', 'email' => 'kloone@orbitflow.test', 'password' => 'OrbitDemo@Siswa07!', 'role' => User::ROLE_SISWA],
            'siswa08' => ['name' => 'Sharkified (Demo)', 'email' => 'sharkified@orbitflow.test', 'password' => 'OrbitDemo@Siswa08!', 'role' => User::ROLE_SISWA],
            'siswa09' => ['name' => 'eastshark (Demo)', 'email' => 'eastshark@orbitflow.test', 'password' => 'OrbitDemo@Siswa09!', 'role' => User::ROLE_SISWA],
            'siswa10' => ['name' => 'Thnnder (Demo)', 'email' => 'thnnder@orbitflow.test', 'password' => 'OrbitDemo@Siswa10!', 'role' => User::ROLE_SISWA],
            'siswa11' => ['name' => 'Rediio (Demo)', 'email' => 'rediio@orbitflow.test', 'password' => 'OrbitDemo@Siswa11!', 'role' => User::ROLE_SISWA],
            'siswa12' => ['name' => 'Cookie (Demo)', 'email' => 'cookie@orbitflow.test', 'password' => 'OrbitDemo@Siswa12!', 'role' => User::ROLE_SISWA],
        ];

        $users = [];

        foreach ($accounts as $key => $account) {
            $studentNumber = $account['role'] === User::ROLE_SISWA
                ? (int) substr($key, -2)
                : null;

            $users[$key] = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    // User's `hashed` cast hashes the plain-text demo password.
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'nis' => $studentNumber ? sprintf('DEMO-RPL-%02d', $studentNumber) : null,
                    'class_name' => $studentNumber
                        ? ($studentNumber % 2 === 0 ? 'XII RPL 2' : 'XII RPL 1')
                        : null,
                    'is_active' => true,
                ]
            );
        }

        return $users;
    }

    /**
     * @return array<string, array<int, EquipmentUnit>>
     */
    private function seedEquipment(): array
    {
        $catalog = [
            ['key' => 'laptop', 'code' => 'LAP', 'name' => 'Laptop', 'category' => 'Komputer', 'description' => 'Laptop untuk kegiatan pembelajaran dan praktik.'],
            ['key' => 'desktop', 'code' => 'PC', 'name' => 'Komputer Desktop', 'category' => 'Komputer', 'description' => 'Komputer desktop untuk praktik laboratorium.'],
            ['key' => 'monitor', 'code' => 'MON', 'name' => 'Monitor', 'category' => 'Komputer', 'description' => 'Monitor untuk workstation laboratorium.'],
            ['key' => 'keyboard', 'code' => 'KEY', 'name' => 'Keyboard', 'category' => 'Komputer', 'description' => 'Keyboard untuk perangkat komputer.'],
            ['key' => 'mouse', 'code' => 'MOU', 'name' => 'Mouse', 'category' => 'Komputer', 'description' => 'Mouse untuk perangkat komputer.'],
            ['key' => 'webcam', 'code' => 'CAM', 'name' => 'Webcam', 'category' => 'Multimedia', 'description' => 'Webcam untuk kegiatan konferensi dan multimedia.'],
            ['key' => 'projector', 'code' => 'PRO', 'name' => 'Proyektor', 'category' => 'Presentasi', 'description' => 'Proyektor untuk presentasi kelas.'],
            ['key' => 'speaker', 'code' => 'SPK', 'name' => 'Speaker', 'category' => 'Multimedia', 'description' => 'Speaker untuk kebutuhan audio kelas.'],
            ['key' => 'microphone', 'code' => 'MIC', 'name' => 'Mikrofon', 'category' => 'Multimedia', 'description' => 'Mikrofon untuk presentasi dan kegiatan sekolah.'],
            ['key' => 'router', 'code' => 'RTR', 'name' => 'Router', 'category' => 'Jaringan', 'description' => 'Router untuk praktik konfigurasi jaringan.'],
            ['key' => 'switch', 'code' => 'SWT', 'name' => 'Switch', 'category' => 'Jaringan', 'description' => 'Switch untuk praktik jaringan komputer.'],
            ['key' => 'hdmi', 'code' => 'HDM', 'name' => 'Kabel HDMI', 'category' => 'Aksesori', 'description' => 'Kabel HDMI untuk koneksi perangkat presentasi.'],
        ];

        $units = [];

        foreach ($catalog as $item) {
            $equipment = Equipment::create([
                'name' => $item['name'],
                'category' => $item['category'],
                'description' => '[DATA DEMO] ' . $item['description'],
                'image_path' => null,
            ]);

            for ($unitNumber = 1; $unitNumber <= 2; $unitNumber++) {
                $units[$item['key']][$unitNumber] = EquipmentUnit::create([
                    'equipment_id' => $equipment->id,
                    'unit_code' => sprintf('OF-%s-%03d', $item['code'], $unitNumber),
                    'condition' => 'Baik',
                    'status' => 'Tersedia',
                    'current_photo_path' => null,
                ]);
            }
        }

        return $units;
    }

    /**
     * @param array<string, User> $users
     * @param array<string, array<int, EquipmentUnit>> $units
     */
    private function seedBorrowings(array $users, array $units): void
    {
        $today = today();
        $now = now();

        $plans = [
            [
                'student' => 'siswa01', 'status' => 'Pending', 'start' => 2, 'end' => 4,
                'purpose' => '[DEMO] Praktik pemrograman web.',
                'items' => [['equipment' => 'laptop'], ['equipment' => 'webcam']],
            ],
            [
                'student' => 'siswa02', 'status' => 'Pending', 'start' => 4, 'end' => 7,
                'purpose' => '[DEMO] Persiapan presentasi kelompok.',
                'items' => [['equipment' => 'projector'], ['equipment' => 'hdmi']],
            ],
            [
                'student' => 'siswa03', 'status' => 'Pending', 'start' => 1, 'end' => 3,
                'purpose' => '[DEMO] Praktik sistem operasi.',
                'items' => [['equipment' => 'desktop']],
            ],
            [
                'student' => 'siswa04', 'status' => 'Borrowed', 'start' => -2, 'end' => 2,
                'purpose' => '[DEMO] Praktik pengembangan aplikasi.',
                'items' => [
                    ['equipment' => 'laptop', 'unit' => 1, 'allocation' => 'active'],
                    ['equipment' => 'mouse', 'unit' => 1, 'allocation' => 'active'],
                ],
            ],
            [
                'student' => 'siswa05', 'status' => 'Borrowed', 'start' => -2, 'end' => 2,
                'purpose' => '[DEMO] Kegiatan presentasi kelas.',
                'items' => [['equipment' => 'projector', 'unit' => 1, 'allocation' => 'loss_pending']],
            ],
            [
                'student' => 'siswa06', 'status' => 'Overdue', 'start' => -8, 'end' => -2,
                'purpose' => '[DEMO] Praktik konfigurasi jaringan.',
                'items' => [
                    ['equipment' => 'router', 'unit' => 1, 'allocation' => 'active'],
                    ['equipment' => 'switch', 'unit' => 1, 'allocation' => 'active'],
                ],
            ],
            [
                'student' => 'siswa07', 'status' => 'Returned', 'start' => -15, 'end' => -10,
                'purpose' => '[DEMO] Praktik perangkat komputer.',
                'items' => [
                    ['equipment' => 'keyboard', 'unit' => 1, 'allocation' => 'returned'],
                    ['equipment' => 'microphone', 'unit' => 1, 'allocation' => 'returned'],
                ],
            ],
            [
                'student' => 'siswa08', 'status' => 'Rejected', 'start' => 1, 'end' => 3,
                'purpose' => '[DEMO] Kegiatan audio kelas.',
                'rejection_reason' => 'Jadwal penggunaan bertabrakan dengan kegiatan sekolah.',
                'items' => [['equipment' => 'speaker']],
            ],
            [
                'student' => 'siswa09', 'status' => 'CompletedWithLoss', 'start' => -6, 'end' => -3,
                'purpose' => '[DEMO] Praktik komputer.',
                'items' => [['equipment' => 'laptop', 'unit' => 2, 'allocation' => 'loss_confirmed']],
            ],
            [
                'student' => 'siswa10', 'status' => 'CompletedWithLoss', 'start' => -7, 'end' => -4,
                'purpose' => '[DEMO] Kegiatan multimedia.',
                'items' => [['equipment' => 'webcam', 'unit' => 1, 'allocation' => 'loss_confirmed']],
            ],
        ];

        foreach ($plans as $plan) {
            $borrowing = Borrowing::create([
                'user_id' => $users[$plan['student']]->id,
                'start_date' => $today->copy()->addDays($plan['start'])->toDateString(),
                'end_date' => $today->copy()->addDays($plan['end'])->toDateString(),
                'purpose' => $plan['purpose'],
                'status' => $plan['status'],
                'rejection_reason' => $plan['rejection_reason'] ?? null,
            ]);

            foreach ($plan['items'] as $itemPlan) {
                $equipment = $units[$itemPlan['equipment']][1]->equipment;
                $item = BorrowingItem::create([
                    'borrowing_id' => $borrowing->id,
                    'equipment_id' => $equipment->id,
                    'quantity' => 1,
                ]);

                $allocationType = $itemPlan['allocation'] ?? null;
                if ($allocationType === null) {
                    continue;
                }

                $unit = $units[$itemPlan['equipment']][$itemPlan['unit'] ?? 1];
                $borrowedAt = $now->copy()->subDays(max(1, abs(min(0, $plan['start']))));
                $returnedAt = $allocationType === 'returned'
                    ? $now->copy()->subDays(5)
                    : null;
                $isOpenActive = in_array($allocationType, ['active', 'loss_pending'], true);
                $isConfirmedLoss = $allocationType === 'loss_confirmed';

                if ($isOpenActive) {
                    $unit->update(['condition' => 'Baik', 'status' => 'Dipinjam']);
                } elseif ($allocationType === 'returned') {
                    $unit->update(['condition' => 'Baik', 'status' => 'Tersedia']);
                } elseif ($isConfirmedLoss) {
                    $unit->update(['condition' => 'Hilang', 'status' => 'Hilang']);
                } else {
                    throw new RuntimeException('Jenis alokasi demo tidak dikenal: ' . $allocationType);
                }

                DB::table('borrowing_item_equipment_unit')->insert([
                    'borrowing_item_id' => $item->id,
                    'equipment_unit_id' => $unit->id,
                    'active_equipment_unit_id' => $isOpenActive ? $unit->id : null,
                    'borrowed_at' => $borrowedAt,
                    'returned_at' => $returnedAt,
                    'condition_at_borrowing' => 'Baik',
                    'handover_photo_path' => null,
                    'handover_photo_taken_by' => null,
                    'created_at' => $borrowedAt,
                    'updated_at' => $returnedAt ?? $borrowedAt,
                ]);

                if ($allocationType === 'returned') {
                    ReturnRecord::create([
                        'borrowing_id' => $borrowing->id,
                        'equipment_unit_id' => $unit->id,
                        'condition' => 'Normal',
                        'notes' => 'Data demo: unit dikembalikan dalam kondisi normal.',
                        'photo_path' => null,
                        'photo_taken_by' => null,
                        'returned_at' => $returnedAt,
                    ]);
                }

                if (in_array($allocationType, ['loss_pending', 'loss_confirmed'], true)) {
                    $isPending = $allocationType === 'loss_pending';
                    $reportedAt = $borrowedAt->copy()->addDay();
                    $loss = Loss::create([
                        'borrowing_id' => $borrowing->id,
                        'equipment_unit_id' => $unit->id,
                        'description' => $isPending
                            ? 'Data demo: unit belum ditemukan setelah kegiatan peminjaman. Laporan menunggu pemeriksaan petugas.'
                            : 'Data demo: unit tidak ditemukan setelah peminjaman dan telah dikonfirmasi oleh petugas.',
                        'status' => $isPending ? 'Dilaporkan' : 'Dikonfirmasi',
                        'reported_at' => $reportedAt,
                        'evidence_photo_path' => null,
                        'reviewed_by' => $isPending ? null : $users['petugas01']->id,
                        'reviewed_at' => $isPending ? null : $reportedAt->copy()->addHours(2),
                        'review_notes' => $isPending ? null : 'Laporan dikonfirmasi dalam dataset demo.',
                    ]);

                    if (! $isPending) {
                        Compensation::create([
                            'user_id' => $users[$plan['student']]->id,
                            'return_record_id' => null,
                            'loss_id' => $loss->id,
                            'type' => 'Kehilangan Alat',
                            'description' => 'Data demo: kehilangan unit ' . $unit->unit_code . ' telah dikonfirmasi. Tindak lanjut menunggu keputusan admin sesuai kebijakan sekolah.',
                            'status' => 'Pending',
                            'resolution_type' => null,
                            'amount' => null,
                            'resolution_notes' => null,
                            'recovered_condition' => null,
                            'decided_by' => null,
                            'decided_at' => null,
                            'resolved_by' => null,
                            'resolved_at' => null,
                            'completion_notes' => null,
                            'replacement_equipment_unit_id' => null,
                        ]);
                    }
                }
            }
        }
    }
}
