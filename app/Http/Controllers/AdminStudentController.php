<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminStudentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,active,inactive,pending'],
        ]);

        $search = trim($validated['q'] ?? '');
        $statusFilter = $validated['status'] ?? 'all';

        $query = User::query()
            ->where('role', User::ROLE_SISWA)
            ->withExists(['studentAccountActivations as has_pending_activation' => fn ($activations) => $activations->whereNull('used_at')])
            ->withCount([
                'borrowings',
                'borrowings as active_borrowings_count' => fn ($borrowings) => $borrowings
                    ->whereIn('status', ['Borrowed', 'Overdue']),
                'borrowings as overdue_borrowings_count' => fn ($borrowings) => $borrowings
                    ->where('status', 'Overdue'),
            ]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('nis', 'like', '%' . $search . '%')
                    ->orWhere('class_name', 'like', '%' . $search . '%');
            });
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'pending') {
            $query->where('is_active', false)
                ->whereHas('studentAccountActivations', fn ($activations) => $activations->whereNull('used_at'));
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false)
                ->whereDoesntHave('studentAccountActivations', fn ($activations) => $activations->whereNull('used_at'));
        }

        $students = $query->orderBy('name')->paginate(15)->withQueryString();
        $studentCount = User::where('role', User::ROLE_SISWA)->count();
        $activeStudentCount = User::where('role', User::ROLE_SISWA)->where('is_active', true)->count();
        $pendingActivationCount = User::where('role', User::ROLE_SISWA)
            ->where('is_active', false)
            ->whereHas('studentAccountActivations', fn ($activations) => $activations->whereNull('used_at'))
            ->count();
        $inactiveStudentCount = User::where('role', User::ROLE_SISWA)
            ->where('is_active', false)
            ->whereDoesntHave('studentAccountActivations', fn ($activations) => $activations->whereNull('used_at'))
            ->count();

        return view('admin.students', compact(
            'students',
            'search',
            'statusFilter',
            'studentCount',
            'activeStudentCount',
            'inactiveStudentCount',
            'pendingActivationCount'
        ));
    }

    public function create()
    {
        return view('admin.student-form', [
            'student' => new User(),
            'isEditing' => false,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
            'nis' => trim((string) $request->input('nis', '')),
            'class_name' => trim((string) $request->input('class_name', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'nis' => ['required', 'string', 'max:50', 'unique:users,nis'],
            'class_name' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'email.unique' => 'Email tersebut sudah digunakan oleh akun lain.',
            'nis.required' => 'NIS wajib diisi untuk akun siswa baru.',
            'nis.unique' => 'NIS tersebut sudah terdaftar.',
            'password.min' => 'Kata sandi harus terdiri dari minimal 10 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $student = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nis' => $validated['nis'],
            'class_name' => $validated['class_name'],
            'password' => $validated['password'],
            'role' => User::ROLE_SISWA,
            'is_active' => true,
        ]);

        ActivityLogger::record(
            'accounts',
            'student.created',
            'Akun siswa ' . $student->name . ' dibuat.',
            $student,
            null,
            ['name' => $student->name, 'email' => $student->email, 'nis' => $student->nis, 'class_name' => $student->class_name, 'is_active' => $student->is_active],
            null,
            $student->name . ' · NIS ' . $student->nis
        );

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'Akun siswa berhasil dibuat. Sampaikan kata sandi awal kepada siswa melalui cara yang aman.');
    }

    public function edit(User $student)
    {
        $this->ensureStudent($student);

        return view('admin.student-form', [
            'student' => $student,
            'isEditing' => true,
        ]);
    }

    public function update(Request $request, User $student)
    {
        $this->ensureStudent($student);

        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'email' => mb_strtolower(trim((string) $request->input('email', ''))),
            'nis' => trim((string) $request->input('nis', '')),
            'class_name' => trim((string) $request->input('class_name', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->id)],
            'nis' => ['required', 'string', 'max:50', Rule::unique('users', 'nis')->ignore($student->id)],
            'class_name' => ['required', 'string', 'max:50'],
        ], [
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS tersebut sudah digunakan oleh akun lain.',
        ]);

        $before = [];
        DB::transaction(function () use ($student, $validated, &$before) {
            $lockedStudent = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $this->ensureStudent($lockedStudent);
            $before = [
                'name' => $lockedStudent->name,
                'email' => $lockedStudent->email,
                'nis' => $lockedStudent->nis,
                'class_name' => $lockedStudent->class_name,
            ];
            $lockedStudent->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'nis' => $validated['nis'],
                'class_name' => $validated['class_name'],
            ]);
        });

        $student = $student->fresh();
        ActivityLogger::record(
            'accounts',
            'student.updated',
            'Data siswa ' . $student->name . ' diperbarui.',
            $student,
            $before,
            ['name' => $student->name, 'email' => $student->email, 'nis' => $student->nis, 'class_name' => $student->class_name],
            null,
            $student->name . ' · NIS ' . $student->nis
        );

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'Informasi siswa berhasil diperbarui.');
    }

    public function toggleStatus(User $student)
    {
        $this->ensureStudent($student);

        if (! $student->is_active && $student->studentAccountActivations()->whereNull('used_at')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account' => 'Akun ini masih menunggu aktivasi siswa. Terbitkan ulang tautan aktivasi jika diperlukan.',
            ]);
        }

        $wasActive = false;
        $isNowActive = DB::transaction(function () use ($student, &$wasActive) {
            $lockedStudent = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $this->ensureStudent($lockedStudent);
            $wasActive = (bool) $lockedStudent->is_active;
            $lockedStudent->update(['is_active' => ! $lockedStudent->is_active]);

            return $lockedStudent->is_active;
        });

        $freshStudent = $student->fresh();
        ActivityLogger::record(
            'accounts',
            'student.status_changed',
            'Status akun siswa ' . $freshStudent->name . ' diubah menjadi ' . ($isNowActive ? 'aktif' : 'nonaktif') . '.',
            $freshStudent,
            ['is_active' => $wasActive],
            ['is_active' => (bool) $isNowActive],
            null,
            $freshStudent->name . ' · NIS ' . $freshStudent->nis
        );

        $message = $isNowActive
            ? 'Akun siswa berhasil diaktifkan.'
            : 'Akun siswa dinonaktifkan. Riwayat peminjaman tetap tersimpan.';

        return redirect()->route('admin.students.index')->with('success', $message);
    }

    private function ensureStudent(User $student): void
    {
        abort_unless($student->isStudent(), 404);
    }
}
