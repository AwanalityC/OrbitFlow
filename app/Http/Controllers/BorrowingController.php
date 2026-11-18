<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BorrowingController extends Controller
{
    public function create()
    {
        $equipment = Equipment::with(['units', 'borrowableUnits'])
            ->orderBy('name')
            ->get();

        return view('siswa.borrowing-form', compact('equipment'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'purpose' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'start_date.required' => 'Tanggal peminjaman wajib diisi.',
            'end_date.required' => 'Tanggal pengembalian wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal pengembalian harus sama atau setelah tanggal peminjaman.',
            'purpose.required' => 'Tujuan penggunaan wajib diisi.',
            'items.required' => 'Minimal satu alat harus dipilih.',
            'items.min' => 'Minimal satu alat harus dipilih.',
            'items.*.equipment_id.required' => 'Alat wajib dipilih.',
            'items.*.equipment_id.exists' => 'Alat yang dipilih tidak valid.',
            'items.*.quantity.required' => 'Jumlah wajib diisi.',
            'items.*.quantity.min' => 'Jumlah alat minimal 1.',
        ]);

        $equipmentIds = collect($validated['items'])
            ->pluck('equipment_id');

        if ($equipmentIds->count() !== $equipmentIds->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'Alat yang sama tidak boleh dipilih lebih dari satu kali.',
            ]);
        }

        foreach ($validated['items'] as $item) {
            $equipment = Equipment::findOrFail($item['equipment_id']);

            $available = EquipmentUnit::query()
                ->availableForBorrowing()
                ->where('equipment_id', $equipment->id)
                ->count();

            if ($item['quantity'] > $available) {
                throw ValidationException::withMessages([
                    'items' => "Jumlah {$equipment->name} yang tersedia hanya {$available} unit.",
                ]);
            }
        }

        $borrowing = DB::transaction(function () use ($validated, $request) {
            $borrowing = Borrowing::create([
                'user_id' => $request->user()->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'purpose' => $validated['purpose'],
                'status' => 'Pending',
            ]);

            foreach ($validated['items'] as $item) {
                $borrowing->items()->create([
                    'equipment_id' => $item['equipment_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $borrowing;
        });

        return redirect()
            ->route('borrowing.success', $borrowing)
            ->with('success', 'Pengajuan peminjaman berhasil dibuat.');
    }

    public function status(Request $request)
    {
        $user = $request->user();

        $user->borrowings()
            ->where('status', 'Borrowed')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => 'Overdue']);

        $query = $user->borrowings()
            ->with(['items.equipment', 'items.units', 'losses.equipmentUnit'])
            ->latest();

        $borrowing = $request->filled('id')
            ? (clone $query)->whereKey($request->integer('id'))->firstOrFail()
            : $query->first();

        return view('siswa.borrowing-status', compact('borrowing'));
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $user->borrowings()
            ->where('status', 'Borrowed')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => 'Overdue']);

        // Keep the student's newest submitted request at the top of the history.
        $borrowings = $user->borrowings()
            ->with(['items.equipment', 'items.units', 'losses.equipmentUnit'])
            ->orderByDesc('created_at')
            ->get();

        return view('siswa.borrowing-history', compact('borrowings'));
    }
}