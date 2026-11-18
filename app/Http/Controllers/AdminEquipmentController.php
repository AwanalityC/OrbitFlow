<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminEquipmentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', 'in:Baik,Rusak Ringan,Rusak Berat,Hilang'],
            'status' => ['nullable', 'in:Tersedia,Dipinjam,Rusak,Tidak Tersedia,Hilang'],
        ]);

        $search = trim($validated['q'] ?? '');
        $categoryFilter = $validated['category'] ?? '';
        $conditionFilter = $validated['condition'] ?? '';
        $statusFilter = $validated['status'] ?? '';

        $query = EquipmentUnit::with('equipment');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('unit_code', 'like', '%' . $search . '%')
                    ->orWhereHas('equipment', function ($equipmentQuery) use ($search) {
                        $equipmentQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('category', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($categoryFilter !== '') {
            $query->whereHas('equipment', fn ($equipmentQuery) => $equipmentQuery->where('category', $categoryFilter));
        }

        if ($conditionFilter !== '') {
            $query->where('condition', $conditionFilter);
        }

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $units = $query->orderBy('equipment_id')
            ->orderBy('unit_code')
            ->paginate(20)
            ->withQueryString();

        $totalUnits = EquipmentUnit::count();
        $availableUnits = EquipmentUnit::availableForBorrowing()->count();
        $borrowedUnits = EquipmentUnit::where('status', 'Dipinjam')->count();
        $repairUnits = EquipmentUnit::whereIn('condition', ['Rusak Ringan', 'Rusak Berat'])->count();
        $lostUnits = EquipmentUnit::where('condition', 'Hilang')->orWhere('status', 'Hilang')->count();
        $equipmentOptions = Equipment::orderBy('name')->get(['id', 'name']);
        $categories = Equipment::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('admin.partials.equipment-unit-results', compact('units'))->render(),
                'count' => $units->total(),
            ]);
        }

        return view('admin.equipment-units', compact(
            'units',
            'totalUnits',
            'availableUnits',
            'borrowedUnits',
            'repairUnits',
            'lostUnits',
            'equipmentOptions',
            'categories',
            'search',
            'categoryFilter',
            'conditionFilter',
            'statusFilter'
        ));
    }

    public function storeEquipment(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'initial_unit_codes' => ['required', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.image' => 'Foto alat harus berupa gambar yang valid.',
            'photo.mimes' => 'Foto alat harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $unitCodes = $this->validatedUnitCodes($validated['initial_unit_codes'], 'initial_unit_codes');
        $photoPath = null;

        try {
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('equipment/catalog', 'public');
                if (! $photoPath) {
                    throw new \RuntimeException('Foto katalog tidak dapat disimpan ke penyimpanan.');
                }
            }

            $equipment = DB::transaction(function () use ($validated, $unitCodes, $photoPath) {
                $equipment = Equipment::create([
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'description' => $validated['description'] ?? null,
                    'image_path' => $photoPath,
                ]);

                foreach ($unitCodes as $code) {
                    EquipmentUnit::create([
                        'equipment_id' => $equipment->id,
                        'unit_code' => $code,
                        'condition' => 'Baik',
                        'status' => 'Tersedia',
                    ]);
                }

                return $equipment;
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            throw $exception;
        }

        ActivityLogger::record(
            'inventory',
            'equipment.created',
            'Alat baru ' . $equipment->name . ' ditambahkan bersama ' . count($unitCodes) . ' unit fisik.',
            $equipment,
            null,
            [
                'name' => $equipment->name,
                'category' => $equipment->category,
                'unit_count' => count($unitCodes),
                'unit_codes' => $unitCodes,
                'has_catalog_photo' => (bool) $photoPath,
            ],
            null,
            $equipment->name . ' · ' . count($unitCodes) . ' unit'
        );

        return redirect()
            ->route('admin.equipment-units.index')
            ->with('success', 'Alat baru dan unit fisiknya berhasil ditambahkan.');
    }

    public function storeUnits(Request $request, Equipment $equipment)
    {
        $validated = $request->validate([
            'additional_unit_codes' => ['required', 'string', 'max:5000'],
        ]);

        $unitCodes = $this->validatedUnitCodes($validated['additional_unit_codes'], 'additional_unit_codes');

        DB::transaction(function () use ($equipment, $unitCodes) {
            foreach ($unitCodes as $code) {
                EquipmentUnit::create([
                    'equipment_id' => $equipment->id,
                    'unit_code' => $code,
                    'condition' => 'Baik',
                    'status' => 'Tersedia',
                ]);
            }
        });

        ActivityLogger::record(
            'inventory',
            'equipment.units_added',
            count($unitCodes) . ' unit ditambahkan ke alat ' . $equipment->name . '.',
            $equipment,
            null,
            ['unit_count' => count($unitCodes), 'unit_codes' => $unitCodes],
            null,
            $equipment->name
        );

        return redirect()
            ->route('admin.equipment-units.index')
            ->with('success', count($unitCodes) . ' unit baru berhasil ditambahkan ke ' . $equipment->name . '.');
    }

    public function updateUnitPhoto(Request $request, EquipmentUnit $unit)
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.required' => 'Foto kondisi unit wajib dipilih.',
            'photo.image' => 'File harus berupa gambar yang valid.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $newPhotoPath = null;
        $previousPhotoPath = null;

        try {
            DB::transaction(function () use ($request, $unit, &$newPhotoPath, &$previousPhotoPath) {
                $lockedUnit = EquipmentUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();

                if ($lockedUnit->status === 'Dipinjam') {
                    throw ValidationException::withMessages([
                        'photo' => 'Unit sedang dipinjam. Foto kondisi harus diperbarui saat pengembalian.',
                    ]);
                }

                $previousPhotoPath = $lockedUnit->current_photo_path;
                $newPhotoPath = $request->file('photo')->store('equipment/units', 'public');
                if (! $newPhotoPath) {
                    throw new \RuntimeException('Foto kondisi unit tidak dapat disimpan ke penyimpanan.');
                }
                $lockedUnit->update(['current_photo_path' => $newPhotoPath]);
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }
            throw $exception;
        }

        // Keep previous images because they may still be referenced by handover/return history.

        $freshUnit = $unit->fresh('equipment');
        ActivityLogger::record(
            'inventory',
            'inventory.photo_updated',
            'Foto kondisi unit ' . $freshUnit->unit_code . ' diperbarui.',
            $freshUnit,
            ['has_photo' => (bool) $previousPhotoPath],
            ['has_photo' => true, 'condition' => $freshUnit->condition],
            null,
            $freshUnit->unit_code . ' · ' . ($freshUnit->equipment?->name ?? 'Alat')
        );

        return redirect()->route('admin.equipment-units.index')
            ->with('success', 'Foto kondisi unit berhasil diperbarui.');
    }

    public function updateCondition(Request $request, EquipmentUnit $unit)
    {
        $validated = $request->validate([
            'condition' => ['required', 'in:Baik,Rusak Ringan,Rusak Berat,Hilang'],
        ], [
            'condition.required' => 'Pilih kondisi unit terlebih dahulu.',
            'condition.in' => 'Kondisi unit tidak valid.',
        ]);

        $previous = [];
        DB::transaction(function () use ($unit, $validated, &$previous) {
            $lockedUnit = EquipmentUnit::whereKey($unit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUnit->status === 'Dipinjam') {
                throw ValidationException::withMessages([
                    'condition' => 'Unit yang sedang dipinjam tidak dapat diubah. Catat pengembalian terlebih dahulu.',
                ]);
            }

            $previous = ['condition' => $lockedUnit->condition, 'status' => $lockedUnit->status];
            $condition = $validated['condition'];
            $status = match ($condition) {
                'Baik', 'Rusak Ringan' => 'Tersedia',
                'Rusak Berat' => 'Tidak Tersedia',
                'Hilang' => 'Hilang',
            };

            $lockedUnit->update([
                'condition' => $condition,
                'status' => $status,
            ]);
        });

        $loggedUnit = $unit->fresh('equipment');
        ActivityLogger::record(
            'inventory',
            'inventory.condition_updated',
            'Kondisi unit ' . $loggedUnit->unit_code . ' diperbarui.',
            $loggedUnit,
            $previous,
            ['condition' => $loggedUnit->condition, 'status' => $loggedUnit->status],
            null,
            $loggedUnit->unit_code . ' · ' . ($loggedUnit->equipment?->name ?? 'Alat')
        );

        $message = match ($validated['condition']) {
            'Baik' => 'Unit ditandai Baik dan tersedia untuk dipinjam.',
            'Rusak Ringan' => 'Unit tetap tersedia dan akan menjadi prioritas kedua setelah unit Baik.',
            'Rusak Berat' => 'Unit ditandai Rusak Berat dan tidak dapat dipinjam sampai diperbaiki.',
            'Hilang' => 'Unit ditandai Hilang dan tidak dapat dipinjam.',
        };

        return redirect()
            ->route('admin.equipment-units.index')
            ->with('success', $message);
    }

    /** @return array<int, string> */
    private function validatedUnitCodes(string $rawCodes, string $field): array
    {
        $codes = collect(preg_split('/\\R/u', $rawCodes) ?: [])
            ->map(fn ($code) => trim($code))
            ->filter(fn ($code) => $code !== '')
            ->values();

        if ($codes->isEmpty()) {
            throw ValidationException::withMessages([
                $field => 'Masukkan minimal satu kode unit, satu kode pada setiap baris.',
            ]);
        }

        if ($codes->count() > 50) {
            throw ValidationException::withMessages([
                $field => 'Maksimal 50 unit dapat ditambahkan dalam satu kali proses.',
            ]);
        }

        if ($codes->contains(fn ($code) => mb_strlen($code) > 255)) {
            throw ValidationException::withMessages([
                $field => 'Setiap kode unit maksimal 255 karakter.',
            ]);
        }

        $normalizedCodes = $codes->map(fn ($code) => mb_strtolower($code));
        if ($normalizedCodes->unique()->count() !== $codes->count()) {
            throw ValidationException::withMessages([
                $field => 'Terdapat kode unit yang sama lebih dari satu kali dalam daftar.',
            ]);
        }

        $existingCode = EquipmentUnit::whereIn('unit_code', $codes->all())->value('unit_code');
        if ($existingCode !== null) {
            throw ValidationException::withMessages([
                $field => "Kode unit {$existingCode} sudah digunakan. Gunakan kode yang berbeda.",
            ]);
        }

        return $codes->all();
    }
}
