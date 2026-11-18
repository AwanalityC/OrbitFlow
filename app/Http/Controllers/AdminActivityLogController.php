<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'in:all,borrowing,returns,inventory,accounts,imports,security,backup'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if (! empty($validated['from']) && ! empty($validated['to']) && $validated['to'] < $validated['from']) {
            return back()->withErrors(['to' => 'Tanggal akhir tidak boleh lebih awal dari tanggal awal.'])->withInput();
        }

        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();

        // Petugas can only view entries they personally performed. Super Admin
        // can review all entries and optionally filter by another administrator.
        $visibleQuery = ActivityLog::query();
        if (! $isSuperAdmin) {
            $visibleQuery->where('actor_user_id', $user->id);
        }

        $query = clone $visibleQuery;
        $search = trim($validated['q'] ?? '');
        $category = $validated['category'] ?? 'all';

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('summary', 'like', '%' . $search . '%')
                    ->orWhere('actor_name', 'like', '%' . $search . '%')
                    ->orWhere('actor_email', 'like', '%' . $search . '%')
                    ->orWhere('subject_label', 'like', '%' . $search . '%')
                    ->orWhere('event_key', 'like', '%' . $search . '%');
            });
        }

        if ($category !== 'all') {
            $query->where('category', $category);
        }

        if ($isSuperAdmin && ! empty($validated['actor_id'])) {
            $query->where('actor_user_id', (int) $validated['actor_id']);
        }

        if (! empty($validated['from'])) {
            $from = Carbon::parse($validated['from'], 'Asia/Jakarta')->startOfDay()->utc();
            $query->where('created_at', '>=', $from);
        }

        if (! empty($validated['to'])) {
            $to = Carbon::parse($validated['to'], 'Asia/Jakarta')->endOfDay()->utc();
            $query->where('created_at', '<=', $to);
        }

        $todayStart = Carbon::now('Asia/Jakarta')->startOfDay()->utc();
        $todayCount = (clone $visibleQuery)->where('created_at', '>=', $todayStart)->count();
        $totalVisible = (clone $visibleQuery)->count();
        $categoryCount = (clone $visibleQuery)->distinct('category')->count('category');

        $actors = collect();
        if ($isSuperAdmin) {
            $actors = ActivityLog::query()
                ->select(['actor_user_id', 'actor_name', 'actor_email'])
                ->whereNotNull('actor_user_id')
                ->orderBy('actor_name')
                ->get()
                ->unique('actor_user_id')
                ->values();
        }

        $activityLogs = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.activity-logs', compact(
            'activityLogs',
            'search',
            'category',
            'todayCount',
            'totalVisible',
            'categoryCount',
            'actors',
            'isSuperAdmin'
        ));
    }

    public function show(Request $request, ActivityLog $activityLog)
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || (int) $activityLog->actor_user_id === (int) $user->id, 403);

        return view('admin.activity-log-detail', compact('activityLog'));
    }
}
