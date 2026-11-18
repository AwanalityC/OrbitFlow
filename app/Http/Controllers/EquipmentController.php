<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $statuses = [
            'Tersedia',
            'Dipinjam',
            'Rusak',
            'Tidak Tersedia',
            'Hilang',
        ];

        $equipment = Equipment::with(['units', 'borrowableUnits'])
            ->orderBy('name')
            ->get()
            ->map(function (Equipment $item) use ($statuses) {
                $unitStatuses = $item->units
                    ->pluck('status')
                    ->filter()
                    ->unique()
                    ->values();

                if ($unitStatuses->isEmpty()) {
                    $unitStatuses = collect(['Tidak Tersedia']);
                } else {
                    $unitStatuses = collect($statuses)
                        ->filter(fn (string $status) => $unitStatuses->contains($status))
                        ->values();
                }

                $availableUnits = $item->borrowableUnits;

                $item->setAttribute('available_unit_count', $availableUnits->count());
                $item->setAttribute('available_normal_count', $availableUnits->where('condition', 'Baik')->count());
                $item->setAttribute('available_minor_damage_count', $availableUnits->where('condition', 'Rusak Ringan')->count());
                $item->setAttribute('total_unit_count', $item->units->count());
                $item->setRelation('catalogStatuses', $unitStatuses);

                return $item;
            });

        $categories = $equipment
            ->pluck('category')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($request->filled('search')) {
            $search = Str::lower(trim($request->string('search')->toString()));

            $equipment = $equipment->filter(function (Equipment $item) use ($search) {
                return Str::contains(Str::lower((string) $item->name), $search)
                    || Str::contains(Str::lower((string) $item->category), $search)
                    || Str::contains(Str::lower((string) $item->description), $search);
            })->values();
        }

        if ($request->filled('category')) {
            $category = $request->string('category')->toString();
            $equipment = $equipment
                ->filter(fn (Equipment $item) => $item->category === $category)
                ->values();
        }

        if ($request->filled('status') && in_array($request->status, $statuses, true)) {
            $status = $request->status;
            $equipment = $equipment
                ->filter(fn (Equipment $item) => $item->catalogStatuses->contains($status))
                ->values();
        }

        return view('siswa.equipment-index', compact(
            'equipment',
            'categories',
            'statuses'
        ));
    }

    public function show($id)
    {
        $equipment = Equipment::with(['units', 'borrowableUnits'])->findOrFail($id);

        $available = $equipment->borrowableUnits->count();

        return view('siswa.equipment-detail', compact(
            'equipment',
            'available'
        ));
    }
}
