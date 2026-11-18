<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\EquipmentUnit;
use App\Models\Loss;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentLossController extends Controller
{
    public function store(Request $request, Borrowing $borrowing, EquipmentUnit $unit)
    {
        abort_unless($borrowing->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'description.required' => 'Jelaskan kejadian kehilangan alat terlebih dahulu.',
            'description.min' => 'Keterangan kehilangan minimal 10 karakter.',
            'evidence_photo.image' => 'Bukti pendukung harus berupa gambar yang valid.',
            'evidence_photo.mimes' => 'Bukti pendukung harus berformat JPG, PNG, atau WEBP.',
            'evidence_photo.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        if (! in_array($borrowing->status, ['Borrowed', 'Overdue'], true)) {
            throw ValidationException::withMessages([
                'description' => 'Laporan kehilangan hanya dapat dibuat untuk peminjaman yang masih aktif.',
            ]);
        }

        $evidencePath = null;

        try {
            if ($request->hasFile('evidence_photo')) {
                $evidencePath = $request->file('evidence_photo')->store('loss-evidence', 'local');
                if (! $evidencePath) {
                    throw new \RuntimeException('Bukti pendukung tidak dapat disimpan.');
                }
            }

            $loss = DB::transaction(function () use ($borrowing, $unit, $validated, $request, $evidencePath) {
                $lockedBorrowing = Borrowing::whereKey($borrowing->id)->lockForUpdate()->firstOrFail();
                $lockedUnit = EquipmentUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();

                if (! in_array($lockedBorrowing->status, ['Borrowed', 'Overdue'], true)) {
                    throw ValidationException::withMessages([
                        'description' => 'Peminjaman sudah tidak aktif. Laporan kehilangan tidak dapat dibuat.',
                    ]);
                }

                $allocation = DB::table('borrowing_item_equipment_unit')
                    ->join('borrowing_items', 'borrowing_items.id', '=', 'borrowing_item_equipment_unit.borrowing_item_id')
                    ->where('borrowing_items.borrowing_id', $lockedBorrowing->id)
                    ->where('borrowing_item_equipment_unit.equipment_unit_id', $lockedUnit->id)
                    ->whereNull('borrowing_item_equipment_unit.returned_at')
                    ->where('borrowing_item_equipment_unit.active_equipment_unit_id', $lockedUnit->id)
                    ->lockForUpdate()
                    ->first();

                if (! $allocation || $lockedUnit->status !== 'Dipinjam') {
                    throw ValidationException::withMessages([
                        'description' => 'Unit ini bukan bagian dari peminjaman aktif yang dapat dilaporkan hilang.',
                    ]);
                }

                $alreadyOpen = Loss::where('equipment_unit_id', $lockedUnit->id)
                    ->whereIn('status', ['Dilaporkan', 'Dikonfirmasi', 'Hilang Permanen'])
                    ->exists();

                if ($alreadyOpen) {
                    throw ValidationException::withMessages([
                        'description' => 'Unit ini sudah memiliki laporan kehilangan yang belum dapat dilaporkan ulang.',
                    ]);
                }

                return Loss::create([
                    'borrowing_id' => $lockedBorrowing->id,
                    'equipment_unit_id' => $lockedUnit->id,
                    'description' => $validated['description'],
                    'status' => 'Dilaporkan',
                    'reported_at' => now(),
                    'evidence_photo_path' => $evidencePath,
                ]);
            });
        } catch (Throwable $exception) {
            if ($evidencePath) {
                Storage::disk('local')->delete($evidencePath);
            }
            throw $exception;
        }

        ActivityLogger::record(
            'losses',
            'loss.reported',
            'Siswa melaporkan kehilangan unit ' . $unit->unit_code . ' pada pengajuan OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . '.',
            $loss,
            null,
            [
                'borrowing_id' => $borrowing->id,
                'equipment_unit_id' => $unit->id,
                'has_evidence_photo' => (bool) $evidencePath,
                'status' => 'Dilaporkan',
            ],
            $request->user(),
            $unit->unit_code
        );

        return redirect()
            ->route('borrowing.status', ['id' => $borrowing->id])
            ->with('success', 'Laporan kehilangan berhasil dikirim dan menunggu pemeriksaan petugas.');
    }
}
