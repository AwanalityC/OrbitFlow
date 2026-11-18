<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Compensation;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminDashboardController extends Controller
{
    public function index()
    {
        Borrowing::where('status', 'Borrowed')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => 'Overdue']);

        $pendingBorrowings = Borrowing::with(['user', 'items.equipment'])
            ->where('status', 'Pending')
            ->latest()
            ->limit(8)
            ->get();

        $activeBorrowings = Borrowing::with([
            'user',
            'items.equipment',
            'items.activeUnits.equipment',
        ])
            ->whereIn('status', ['Borrowed', 'Overdue'])
            ->orderByRaw("CASE WHEN status = 'Overdue' THEN 0 ELSE 1 END")
            ->orderBy('end_date')
            ->limit(8)
            ->get();

        $pendingCount = Borrowing::where('status', 'Pending')->count();
        $activeCount = Borrowing::where('status', 'Borrowed')->count();
        $overdueCount = Borrowing::where('status', 'Overdue')->count();
        $totalBorrowings = Borrowing::count();
        $totalUnits = EquipmentUnit::count();
        $availableUnits = EquipmentUnit::availableForBorrowing()->count();
        $repairUnits = EquipmentUnit::whereIn('condition', ['Rusak Ringan', 'Rusak Berat'])
            ->count();
        $lostUnits = EquipmentUnit::where('condition', 'Hilang')->orWhere('status', 'Hilang')->count();
        $studentCount = User::where('role', 'siswa')->count();
        $openCompensationStatuses = ['Pending', 'Menunggu Pembayaran', 'Menunggu Penggantian'];
        $pendingCompensationCount = Compensation::whereIn('status', $openCompensationStatuses)->count();
        $pendingLossCount = Loss::where('status', 'Dilaporkan')->count();
        $pendingCompensations = Compensation::with([
            'user',
            'returnRecord.equipmentUnit',
            'loss.equipmentUnit',
        ])
            ->whereIn('status', $openCompensationStatuses)
            ->latest()
            ->limit(5)
            ->get();

        $dueSoonCount = Borrowing::where('status', 'Borrowed')
            ->whereDate('end_date', '>=', today()->toDateString())
            ->whereDate('end_date', '<=', today()->addDays(3)->toDateString())
            ->count();

        return view('admin.dashboard', compact(
            'pendingBorrowings',
            'activeBorrowings',
            'pendingCount',
            'activeCount',
            'overdueCount',
            'totalBorrowings',
            'totalUnits',
            'availableUnits',
            'repairUnits',
            'lostUnits',
            'studentCount',
            'pendingCompensations',
            'pendingCompensationCount',
            'pendingLossCount',
            'dueSoonCount'
        ));
    }

    public function review(Borrowing $borrowing)
    {
        $borrowing->load([
            'user',
            'items.equipment.units',
            'items.equipment.borrowableUnits',
            'items.activeUnits.equipment',
            'returnRecords.equipmentUnit',
        ]);

        return view('admin.borrowing-review', compact('borrowing'));
    }

    public function approve(Request $request, Borrowing $borrowing)
    {
        if ($borrowing->status !== 'Pending') {
            abort(400, 'Pengajuan ini sudah diproses.');
        }

        $borrowing->loadMissing('items');
        $photoRules = ['handover_photos' => ['required', 'array']];
        foreach ($borrowing->items as $item) {
            $photoRules['handover_photos.' . $item->equipment_id] = [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ];
        }

        $request->validate($photoRules, [
            'handover_photos.required' => 'Foto kondisi saat penyerahan wajib diunggah.',
            'handover_photos.*.required' => 'Unggah foto kondisi untuk setiap jenis alat yang diserahkan.',
            'handover_photos.*.image' => 'Berkas foto penyerahan harus berupa gambar yang valid.',
            'handover_photos.*.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'handover_photos.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
        ]);

        $handoverPhotoPaths = [];

        try {
            foreach ($borrowing->items as $item) {
                $file = $request->file('handover_photos.' . $item->equipment_id);
                $storedPath = $file->store('equipment/handovers', 'public');
                if (! $storedPath) {
                    throw new \RuntimeException('Foto penyerahan tidak dapat disimpan ke penyimpanan.');
                }
                $handoverPhotoPaths[$item->equipment_id] = $storedPath;
            }

            // Retry a small number of times if two multi-item approvals deadlock.
            DB::transaction(function () use ($borrowing, $handoverPhotoPaths, $request) {
                $lockedBorrowing = Borrowing::whereKey($borrowing->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedBorrowing->status !== 'Pending') {
                    throw ValidationException::withMessages([
                        'availability' => 'Pengajuan ini sudah diproses.',
                    ]);
                }

                // Always lock equipment in a stable order to reduce deadlocks.
                $items = $lockedBorrowing->items()
                    ->with('equipment')
                    ->orderBy('equipment_id')
                    ->orderBy('id')
                    ->get();

                foreach ($items as $item) {
                    $units = EquipmentUnit::query()
                        ->where('equipment_id', $item->equipment_id)
                        ->where('status', 'Tersedia')
                        ->whereIn('condition', ['Baik', 'Rusak Ringan'])
                        ->whereNotExists(function ($activeAllocations) {
                            $activeAllocations->selectRaw('1')
                                ->from('borrowing_item_equipment_unit as active_allocations')
                                ->whereColumn(
                                    'active_allocations.active_equipment_unit_id',
                                    'equipment_units.id'
                                );
                        })
                        ->orderByRaw("CASE WHEN `condition` = 'Baik' THEN 0 WHEN `condition` = 'Rusak Ringan' THEN 1 ELSE 2 END")
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->limit($item->quantity)
                        ->get();

                    if ($units->count() < $item->quantity) {
                        throw ValidationException::withMessages([
                            'availability' =>
                                "Stok {$item->equipment->name} tidak mencukupi. " .
                                "Tersedia {$units->count()} unit, " .
                                "pengajuan membutuhkan {$item->quantity} unit.",
                        ]);
                    }

                    $handoverPhotoPath = $handoverPhotoPaths[$item->equipment_id];

                    foreach ($units as $unit) {
                        $item->units()->attach($unit->id, [
                            'borrowed_at' => now(),
                            'returned_at' => null,
                            'condition_at_borrowing' => $unit->condition,
                            'active_equipment_unit_id' => $unit->id,
                            'handover_photo_path' => $handoverPhotoPath,
                            'handover_photo_taken_by' => $request->user()->id,
                        ]);

                        $unit->update([
                            'status' => 'Dipinjam',
                            'current_photo_path' => $handoverPhotoPath,
                        ]);
                    }
                }

                $lockedBorrowing->update(['status' => 'Borrowed']);
            }, 3);
        } catch (Throwable $exception) {
            // Filesystem writes are not part of the SQL transaction; remove snapshots if allocation rolls back.
            foreach (array_unique($handoverPhotoPaths) as $path) {
                Storage::disk('public')->delete($path);
            }

            if ($exception instanceof QueryException && $this->isActiveUnitAllocationConflict($exception)) {
                throw ValidationException::withMessages([
                    'availability' => 'Satu atau beberapa unit baru saja dialokasikan ke pengajuan lain. Periksa ketersediaan lalu coba lagi.',
                ]);
            }

            throw $exception;
        }

        $loggedBorrowing = $borrowing->fresh(['user']);
        ActivityLogger::record(
            'borrowing',
            'borrowing.approved',
            'Pengajuan OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . ' disetujui.',
            $loggedBorrowing,
            ['status' => 'Pending'],
            [
                'status' => 'Borrowed',
                'start_date' => $loggedBorrowing->start_date?->toDateString(),
                'end_date' => $loggedBorrowing->end_date?->toDateString(),
                'student_name' => $loggedBorrowing->user?->name,
                'handover_photo_count' => count($handoverPhotoPaths),
            ],
            null,
            'OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . ' · ' . ($loggedBorrowing->user?->name ?? 'Peminjam')
        );

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Pengajuan berhasil disetujui dan foto kondisi penyerahan tersimpan.');
    }

    private function isActiveUnitAllocationConflict(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'bi_active_unit_unique')
            || (str_contains($message, 'active_equipment_unit_id')
                && (str_contains($message, 'unique') || str_contains($message, 'duplicate')));
    }

    public function reject(Request $request, Borrowing $borrowing)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 1000 karakter.',
        ]);

        DB::transaction(function () use ($borrowing, $validated) {
            $lockedBorrowing = Borrowing::whereKey($borrowing->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBorrowing->status !== 'Pending') {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Pengajuan ini sudah diproses.',
                ]);
            }

            $lockedBorrowing->update([
                'status' => 'Rejected',
                'rejection_reason' => trim($validated['rejection_reason']),
            ]);
        });

        $loggedBorrowing = $borrowing->fresh(['user']);
        ActivityLogger::record(
            'borrowing',
            'borrowing.rejected',
            'Pengajuan OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . ' ditolak.',
            $loggedBorrowing,
            ['status' => 'Pending'],
            [
                'status' => 'Rejected',
                'rejection_reason' => trim($validated['rejection_reason']),
                'student_name' => $loggedBorrowing->user?->name,
            ],
            null,
            'OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . ' · ' . ($loggedBorrowing->user?->name ?? 'Peminjam')
        );

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Pengajuan berhasil ditolak dan alasannya disimpan.');
    }
}
