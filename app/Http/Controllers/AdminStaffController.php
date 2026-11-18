<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminStaffController extends Controller
{
    private const STAFF_ROLES = ['super_admin', 'admin', 'petugas'];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'in:all,super_admin,petugas'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);

        $search = trim($validated['q'] ?? '');
        $roleFilter = $validated['role'] ?? 'all';
        $statusFilter = $validated['status'] ?? 'all';

        $query = User::query()->whereIn('role', self::STAFF_ROLES);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if ($roleFilter === 'super_admin') {
            $query->whereIn('role', [User::ROLE_SUPER_ADMIN, 'admin']);
        } elseif ($roleFilter === 'petugas') {
            $query->where('role', User::ROLE_PETUGAS);
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $staffMembers = $query->orderByRaw("CASE WHEN role IN ('super_admin', 'admin') THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $staffCount = User::whereIn('role', self::STAFF_ROLES)->count();
        $activeStaffCount = User::whereIn('role', self::STAFF_ROLES)->where('is_active', true)->count();
        $inactiveStaffCount = User::whereIn('role', self::STAFF_ROLES)->where('is_active', false)->count();
        $superAdminCount = User::whereIn('role', [User::ROLE_SUPER_ADMIN, 'admin'])->where('is_active', true)->count();

        return view('admin.staff', compact(
            'staffMembers',
            'search',
            'roleFilter',
            'statusFilter',
            'staffCount',
            'activeStaffCount',
            'inactiveStaffCount',
            'superAdminCount'
        ));
    }

    public function create()
    {
        return view('admin.staff-form', [
            'staff' => new User(),
            'isEditing' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_PETUGAS])],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'email.unique' => 'Email tersebut sudah digunakan oleh akun lain.',
            'role.in' => 'Pilih peran staf yang valid.',
            'password.min' => 'Kata sandi harus terdiri dari minimal 10 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        ActivityLogger::record(
            'accounts',
            'staff.created',
            'Akun ' . $staff->role_label . ' ' . $staff->name . ' dibuat.',
            $staff,
            null,
            ['name' => $staff->name, 'email' => $staff->email, 'role' => $staff->role, 'is_active' => $staff->is_active],
            null,
            $staff->name . ' · ' . $staff->role_label
        );

        return redirect()
            ->route('admin.staff.index')
            ->with('success', 'Akun staf berhasil dibuat. Sampaikan kata sandi awal melalui cara yang aman.');
    }

    public function edit(User $staff)
    {
        $this->ensureStaff($staff);

        return view('admin.staff-form', [
            'staff' => $staff,
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $this->ensureStaff($staff);

        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'role' => ['required', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_PETUGAS])],
        ]);

        $before = [];
        DB::transaction(function () use ($staff, $validated, &$before) {
            $lockedStaff = User::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $before = [
                'name' => $lockedStaff->name,
                'email' => $lockedStaff->email,
                'role' => $lockedStaff->role,
            ];
            $isSelf = $lockedStaff->id === auth()->id();
            $wasActiveSuperAdmin = $lockedStaff->is_active && $lockedStaff->isSuperAdmin();
            $willRemainSuperAdmin = $validated['role'] === User::ROLE_SUPER_ADMIN;

            if ($isSelf && ! $willRemainSuperAdmin) {
                throw ValidationException::withMessages([
                    'role' => 'Anda tidak dapat mengubah peran akun Super Admin yang sedang digunakan.',
                ]);
            }

            if ($wasActiveSuperAdmin && ! $willRemainSuperAdmin) {
                $this->ensureAnotherActiveSuperAdminExists($lockedStaff->id);
            }

            $lockedStaff->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ]);
        });

        $freshStaff = $staff->fresh();
        ActivityLogger::record(
            'accounts',
            'staff.updated',
            'Data akun staf ' . $freshStaff->name . ' diperbarui.',
            $freshStaff,
            $before,
            ['name' => $freshStaff->name, 'email' => $freshStaff->email, 'role' => $freshStaff->role],
            null,
            $freshStaff->name . ' · ' . $freshStaff->role_label
        );

        return redirect()->route('admin.staff.index')->with('success', 'Informasi akun staf berhasil diperbarui.');
    }

    public function toggleStatus(User $staff)
    {
        $this->ensureStaff($staff);

        if ($staff->id === auth()->id()) {
            throw ValidationException::withMessages([
                'account' => 'Anda tidak dapat menonaktifkan akun yang sedang digunakan.',
            ]);
        }

        $wasActive = false;
        DB::transaction(function () use ($staff, &$wasActive) {
            $lockedStaff = User::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $wasActive = (bool) $lockedStaff->is_active;

            if ($lockedStaff->is_active && $lockedStaff->isSuperAdmin()) {
                $this->ensureAnotherActiveSuperAdminExists($lockedStaff->id);
            }

            $lockedStaff->update(['is_active' => ! $lockedStaff->is_active]);
        });

        $freshStaff = $staff->fresh();
        ActivityLogger::record(
            'accounts',
            'staff.status_changed',
            'Status akun staf ' . $freshStaff->name . ' diubah menjadi ' . ($freshStaff->is_active ? 'aktif' : 'nonaktif') . '.',
            $freshStaff,
            ['is_active' => $wasActive],
            ['is_active' => (bool) $freshStaff->is_active],
            null,
            $freshStaff->name . ' · ' . $freshStaff->role_label
        );

        $message = $freshStaff->is_active
            ? 'Akun staf berhasil diaktifkan.'
            : 'Akun staf dinonaktifkan.';

        return redirect()->route('admin.staff.index')->with('success', $message);
    }

    private function ensureStaff(User $staff): void
    {
        abort_unless($staff->isAdmin(), 404);
    }

    private function ensureAnotherActiveSuperAdminExists(int $currentStaffId): void
    {
        $otherActiveSuperAdmins = User::query()
            ->whereIn('role', [User::ROLE_SUPER_ADMIN, 'admin'])
            ->where('is_active', true)
            ->where('id', '!=', $currentStaffId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        if ($otherActiveSuperAdmins < 1) {
            throw ValidationException::withMessages([
                'account' => 'Minimal harus ada satu Super Admin aktif. Tambahkan atau aktifkan Super Admin lain terlebih dahulu.',
            ]);
        }
    }
}
