<?php

namespace App\Http\Controllers;

use App\Models\StudentAccountActivation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentActivationController extends Controller
{
    public function show(string $token)
    {
        $activation = $this->findValidActivation($token);
        if (! $activation) {
            return response()->view('auth.student-activation-invalid', [], 410);
        }

        return response()
            ->view('auth.student-activation', ['student' => $activation->student, 'token' => $token])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function activate(Request $request, string $token)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'password.min' => 'Kata sandi harus terdiri dari minimal 10 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $activated = DB::transaction(function () use ($token, $validated) {
            $activation = StudentAccountActivation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $activation || $activation->used_at || $activation->expires_at->isPast()) {
                return false;
            }

            $student = User::query()->whereKey($activation->user_id)->lockForUpdate()->first();
            if (! $student || ! $student->isStudent() || $student->is_active) {
                return false;
            }

            $student->update([
                'password' => $validated['password'],
                'password_changed_at' => now(),
                'is_active' => true,
            ]);

            $student->studentAccountActivations()
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return $student->id;
        });

        if (! $activated) {
            return response()->view('auth.student-activation-invalid', [], 410);
        }

        $activatedStudent = User::find($activated);
        if ($activatedStudent) {
            ActivityLogger::record(
                'accounts',
                'student.activated',
                'Akun siswa ' . $activatedStudent->name . ' berhasil diaktifkan melalui tautan sekolah.',
                $activatedStudent,
                ['is_active' => false],
                ['is_active' => true, 'nis' => $activatedStudent->nis, 'class_name' => $activatedStudent->class_name],
                $activatedStudent,
                $activatedStudent->name . ' · NIS ' . $activatedStudent->nis
            );
        }

        return redirect()->route('login')->with('activation_success', 'Akun berhasil diaktifkan. Silakan masuk menggunakan email dan kata sandi baru.');
    }

    private function findValidActivation(string $token): ?StudentAccountActivation
    {
        if (! preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            return null;
        }

        return StudentAccountActivation::query()
            ->with('student')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->whereHas('student', fn ($query) => $query
                ->where('role', User::ROLE_SISWA)
                ->where('is_active', false))
            ->first();
    }
}
