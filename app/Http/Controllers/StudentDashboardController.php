<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentUnit;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Refresh overdue state when the student returns to the application.
        $request->user()->borrowings()
            ->where('status', 'Borrowed')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => 'Overdue']);

        $query = Equipment::with(['units', 'borrowableUnits']);

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('category', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            if ($request->status === 'Tersedia') {
                $query->whereHas('borrowableUnits');
            } else {
                $query->whereHas('units', function ($q) use ($request) {
                    $q->where('status', $request->status);
                });
            }
        }

        $equipment = $query->latest()->get();

        $categories = Equipment::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        // Read-only inventory overview for the dashboard; borrowing rules remain unchanged.
        $totalEquipment = Equipment::count();
        $availableUnitsCount = EquipmentUnit::availableForBorrowing()->count();
        $borrowedUnitsCount = EquipmentUnit::where('status', 'Dipinjam')->count();
        $categoryCount = Equipment::query()
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->distinct()
            ->count('category');

        $statuses = [
            'Tersedia',
            'Dipinjam',
            'Rusak',
            'Tidak Tersedia',
            'Hilang',
        ];

        return view('siswa.dashboard', compact(
            'equipment',
            'categories',
            'statuses',
            'totalEquipment',
            'availableUnitsCount',
            'borrowedUnitsCount',
            'categoryCount'
        ));
    }
}