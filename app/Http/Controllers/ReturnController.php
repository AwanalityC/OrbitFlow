<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Compensation;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Models\ReturnRecord;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReturnController extends Controller
{
    public function show(Borrowing $borrowing)
    {
        abort_unless(in_array($borrowing->status, ['Borrowed', 'Overdue'], true), 400,
            'Pengajuan ini tidak memiliki alat yang sedang dipinjam.');

        $borrowing->load([
            'user',
            'items.equipment',
            'items.activeUnits',
        ]);

        return view('admin.borrowing-return', compact('borrowing'));
    }

    public function store(Request $request, Borrowing $borrowing)
    {
        $validated = $request->validate([
            'returned_units' => ['required', 'array', 'min:1'],
            'returned_units.*' => ['required', 'integer', 'distinct', 'exists:equipment_units,id'],
            'conditions' => ['required', 'array'],
            'conditions.*' => ['required', 'in:Normal,Rusak Ringan,Rusak Berat'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:1000'],
        ], [
            'returned_units.required' => 'Pilih minimal satu unit yang dikembalikan.',
            'returned_units.min' => 'Pilih minimal satu unit yang dikembalikan.',
            'conditions.*.in' => 'Kondisi unit tidak valid.',
        ]);

        $returnedIds = collect($validated['returned_units'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($returnedIds->count() !== count($validated['returned_units'])) {
            throw ValidationException::withMessages([
                'returned_units' => 'Unit yang sama tidak boleh dikirim dua kali.',
            ]);
        }

        $photoRules = ['photos' => ['required', 'array']];
        foreach ($returnedIds as $unitId) {
            $photoRules['photos.' . $unitId] = [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ];
        }

        $request->validate($photoRules, [
            'photos.required' => 'Foto kondisi terkini wajib diunggah untuk setiap unit yang dikembalikan.',
            'photos.*.required' => 'Foto kondisi setelah pengembalian wajib diisi untuk setiap unit yang dipilih.',
            'photos.*.image' => 'Berkas foto pengembalian harus berupa gambar yang valid.',
            'photos.*.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 5 MB.',
        ]);

        $photoPaths = [];
        $previousBorrowingStatus = null;
        try {
            foreach ($returnedIds as $unitId) {
                $storedPath = $request->file('photos.' . $unitId)->store('equipment/returns', 'public');
                if (! $storedPath) {
                    throw new \RuntimeException('Foto pengembalian tidak dapat disimpan ke penyimpanan.');
                }
                $photoPaths[$unitId] = $storedPath;
            }

            DB::transaction(function () use ($borrowing, $validated, $returnedIds, $photoPaths, $request, &$previousBorrowingStatus) {
                $lockedBorrowing = Borrowing::whereKey($borrowing->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($lockedBorrowing->status, ['Borrowed', 'Overdue'], true)) {
                    throw ValidationException::withMessages([
                        'returned_units' => 'Pengajuan ini sudah selesai atau tidak bisa dikembalikan.',
                    ]);
                }
                $previousBorrowingStatus = $lockedBorrowing->status;

                $allocations = DB::table('borrowing_item_equipment_unit')
                    ->join('borrowing_items', 'borrowing_items.id', '=', 'borrowing_item_equipment_unit.borrowing_item_id')
                    ->where('borrowing_items.borrowing_id', $lockedBorrowing->id)
                    ->whereNull('borrowing_item_equipment_unit.returned_at')
                    ->whereNotNull('borrowing_item_equipment_unit.active_equipment_unit_id')
                    ->whereIn('borrowing_item_equipment_unit.equipment_unit_id', $returnedIds)
                    ->select([
                        'borrowing_item_equipment_unit.borrowing_item_id',
                        'borrowing_item_equipment_unit.equipment_unit_id',
                        'borrowing_item_equipment_unit.condition_at_borrowing',
                    ])
                    ->lockForUpdate()
                    ->get();

                $pendingLossExists = Loss::where('borrowing_id', $lockedBorrowing->id)
                    ->whereIn('equipment_unit_id', $returnedIds)
                    ->where('status', 'Dilaporkan')
                    ->exists();

                if ($pendingLossExists) {
                    throw ValidationException::withMessages([
                        'returned_units' => 'Selesaikan pemeriksaan laporan kehilangan untuk unit yang dipilih sebelum mencatat pengembalian.',
                    ]);
                }

                if ($allocations->count() !== $returnedIds->count()) {
                    throw ValidationException::withMessages([
                        'returned_units' => 'Satu atau beberapa unit bukan bagian dari peminjaman aktif ini.',
                    ]);
                }

                foreach ($allocations as $allocation) {
                    $unitId = (int) $allocation->equipment_unit_id;
                    $condition = $validated['conditions'][$unitId] ?? null;

                    if (! in_array($condition, ['Normal', 'Rusak Ringan', 'Rusak Berat'], true)) {
                        throw ValidationException::withMessages([
                            "conditions.{$unitId}" => 'Pilih kondisi yang valid untuk setiap unit yang dikembalikan.',
                        ]);
                    }

                    $unit = EquipmentUnit::whereKey($unitId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $notes = $validated['notes'][$unitId] ?? null;
                    $returnedAt = now();
                    $photoPath = $photoPaths[$unitId];

                    $returnRecord = ReturnRecord::create([
                        'borrowing_id' => $lockedBorrowing->id,
                        'equipment_unit_id' => $unit->id,
                        'condition' => $condition,
                        'notes' => $notes,
                        'photo_path' => $photoPath,
                        'photo_taken_by' => $request->user()->id,
                        'returned_at' => $returnedAt,
                    ]);

                    if ($condition === 'Normal') {
                        $unit->update([
                            'condition' => 'Baik',
                            'status' => 'Tersedia',
                            'current_photo_path' => $photoPath,
                        ]);
                    } elseif ($condition === 'Rusak Ringan') {
                        $unit->update([
                            'condition' => 'Rusak Ringan',
                            'status' => 'Tersedia',
                            'current_photo_path' => $photoPath,
                        ]);

                        if ($allocation->condition_at_borrowing === 'Baik') {
                            Compensation::create([
                                'user_id' => $lockedBorrowing->user_id,
                                'return_record_id' => $returnRecord->id,
                                'loss_id' => null,
                                'type' => 'Kerusakan Ringan',
                                'description' => 'Unit ' . $unit->unit_code . ' tercatat mengalami kerusakan ringan saat pengembalian. Tindak lanjut mengikuti kebijakan sekolah.',
                                'status' => 'Pending',
                            ]);
                        }
                    } else {
                        $unit->update([
                            'condition' => 'Rusak Berat',
                            'status' => 'Tidak Tersedia',
                            'current_photo_path' => $photoPath,
                        ]);

                        if (in_array($allocation->condition_at_borrowing, ['Baik', 'Rusak Ringan'], true)) {
                            Compensation::create([
                                'user_id' => $lockedBorrowing->user_id,
                                'return_record_id' => $returnRecord->id,
                                'loss_id' => null,
                                'type' => 'Kerusakan Berat',
                                'description' => 'Unit ' . $unit->unit_code . ' mengalami kerusakan berat saat pengembalian. Tindak lanjut mengikuti kebijakan sekolah.',
                                'status' => 'Pending',
                            ]);
                        }
                    }

                    DB::table('borrowing_item_equipment_unit')
                        ->where('borrowing_item_id', $allocation->borrowing_item_id)
                        ->where('equipment_unit_id', $unitId)
                        ->whereNull('returned_at')
                        ->update([
                            'returned_at' => $returnedAt,
                            'active_equipment_unit_id' => null,
                            'updated_at' => $returnedAt,
                        ]);
                }

                $stillBorrowed = DB::table('borrowing_item_equipment_unit')
                    ->join('borrowing_items', 'borrowing_items.id', '=', 'borrowing_item_equipment_unit.borrowing_item_id')
                    ->where('borrowing_items.borrowing_id', $lockedBorrowing->id)
                    ->whereNotNull('borrowing_item_equipment_unit.active_equipment_unit_id')
                    ->exists();

                if (! $stillBorrowed) {
                    $hasConfirmedLoss = Loss::where('borrowing_id', $lockedBorrowing->id)
                        ->whereIn('status', ['Dikonfirmasi', 'Hilang Permanen'])
                        ->exists();

                    $lockedBorrowing->update([
                        'status' => $hasConfirmedLoss ? 'CompletedWithLoss' : 'Returned',
                    ]);
                }
            });
        } catch (Throwable $exception) {
            foreach (array_unique($photoPaths) as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }

        $loggedBorrowing = $borrowing->fresh(['user']);
        $returnedUnitDetails = EquipmentUnit::with('equipment')
            ->whereIn('id', $returnedIds)
            ->get()
            ->map(fn (EquipmentUnit $unit) => [
                'unit_code' => $unit->unit_code,
                'equipment' => $unit->equipment?->name,
                'condition' => $unit->condition,
                'status' => $unit->status,
                'photo_saved' => (bool) $unit->current_photo_path,
                'return_condition' => $validated['conditions'][$unit->id] ?? null,
            ])
            ->values()
            ->all();

        ActivityLogger::record(
            'returns',
            'return.processed',
            count($returnedUnitDetails) . ' unit dikembalikan untuk pengajuan OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . '.',
            $loggedBorrowing,
            ['borrowing_status' => $previousBorrowingStatus],
            ['borrowing_status' => $loggedBorrowing->status, 'returned_units' => $returnedUnitDetails],
            null,
            'OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . ' · ' . ($loggedBorrowing->user?->name ?? 'Peminjam')
        );

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Pengembalian berhasil dicatat. Foto kondisi setiap unit juga berhasil disimpan.');
    }
}
