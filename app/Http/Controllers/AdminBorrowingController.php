<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminBorrowingController extends Controller
{
    public function index(Request $request)
    {
        Borrowing::where('status', 'Borrowed')
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['status' => 'Overdue']);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,pending,active,Pending,Approved,Borrowed,Overdue,Returned,Rejected,CompletedWithLoss'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if (! empty($validated['from']) && ! empty($validated['to']) && $validated['to'] < $validated['from']) {
            return back()
                ->withErrors(['to' => 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.'])
                ->withInput();
        }

        $search = trim($validated['q'] ?? '');
        $status = $validated['status'] ?? 'all';

        $query = Borrowing::with(['user', 'items.equipment']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                if (ctype_digit($search)) {
                    $builder->where('id', (int) $search);
                }

                $builder->orWhere('purpose', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('items.equipment', function ($equipmentQuery) use ($search) {
                        $equipmentQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($status === 'pending') {
            $query->where('status', 'Pending');
        } elseif ($status === 'active') {
            $query->whereIn('status', ['Borrowed', 'Overdue']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! empty($validated['from'])) {
            $fromUtc = Carbon::parse($validated['from'], 'Asia/Jakarta')->startOfDay()->utc();
            $query->where('created_at', '>=', $fromUtc);
        }

        if (! empty($validated['to'])) {
            $toUtc = Carbon::parse($validated['to'], 'Asia/Jakarta')->endOfDay()->utc();
            $query->where('created_at', '<=', $toUtc);
        }

        $borrowings = $query->latest()->paginate(15)->withQueryString();

        return view('admin.borrowings', compact('borrowings', 'search', 'status'));
    }
}
