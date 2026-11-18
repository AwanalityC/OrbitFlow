<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\StudentDashboardController;
use App\Models\Borrowing;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminEquipmentController;
use App\Http\Controllers\AdminBorrowingController;
use App\Http\Controllers\AdminStudentController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminStudentImportController;
use App\Http\Controllers\StudentActivationController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\AdminActivityLogController;
use App\Http\Controllers\AdminBackupController;
use App\Http\Controllers\AdminLossController;
use App\Http\Controllers\StudentLossController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return view('welcome');
})->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login']);

Route::get('/lupa-password', [PasswordRecoveryController::class, 'showRequestForm'])
    ->name('password.request');
Route::post('/lupa-password', [PasswordRecoveryController::class, 'sendResetLink'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'showResetForm'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('password.reset');
Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])
    ->middleware('throttle:5,1')
    ->name('password.update');

Route::get('/aktivasi-siswa/{token}', [StudentActivationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('student.activation.show');
Route::post('/aktivasi-siswa/{token}', [StudentActivationController::class, 'activate'])
    ->middleware('throttle:5,1')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('student.activation.activate');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth');

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->isAdmin()) {
        return redirect('/admin/dashboard');
    }

    if ($user->isStudent()) {
        return redirect('/siswa/dashboard');
    }

    abort(403, 'Peran akun tidak dikenali. Hubungi administrator sekolah.');
})->middleware(['auth', 'active']);

Route::middleware(['auth', 'active', 'role:siswa'])->group(function () {

    Route::get('/siswa/alat', [EquipmentController::class, 'index'])
        ->name('equipment.index');

    Route::get('/peminjaman/status', [BorrowingController::class, 'status'])
        ->name('borrowing.status');

    Route::get('/peminjaman/riwayat', [BorrowingController::class, 'history'])
        ->name('borrowing.history');

    Route::get('/siswa/dashboard', [StudentDashboardController::class, 'index'])
        ->name('siswa.dashboard');

    Route::get('/alat/{id}', [EquipmentController::class, 'show'])
        ->name('equipment.show');

    Route::get('/peminjaman/create', [BorrowingController::class, 'create'])
        ->name('borrowing.create');

    Route::post('/peminjaman', [BorrowingController::class, 'store'])
        ->name('borrowing.store');

    Route::post('/peminjaman/{borrowing}/unit/{unit}/kehilangan', [StudentLossController::class, 'store'])
        ->whereNumber('borrowing')->whereNumber('unit')
        ->middleware('throttle:5,1')
        ->name('borrowing.losses.store');

    Route::get('/peminjaman/berhasil/{borrowing}', function (Borrowing $borrowing) {
        abort_unless($borrowing->user_id === auth()->id(), 403);

        return view('siswa.borrowing-success', compact('borrowing'));
    })->name('borrowing.success');
});

Route::middleware(['auth', 'active', 'role:admin'])->group(function () {

    Route::get('/admin/kehilangan', [AdminLossController::class, 'index'])
        ->name('admin.losses.index');
    Route::get('/admin/kehilangan/{loss}/bukti', [AdminLossController::class, 'evidence'])
        ->whereNumber('loss')
        ->name('admin.losses.evidence');
    Route::post('/admin/kehilangan/{loss}/konfirmasi', [AdminLossController::class, 'confirm'])
        ->whereNumber('loss')
        ->name('admin.losses.confirm');
    Route::post('/admin/kehilangan/{loss}/tolak', [AdminLossController::class, 'reject'])
        ->whereNumber('loss')
        ->name('admin.losses.reject');
    Route::post('/admin/kehilangan/{loss}/kompensasi', [AdminLossController::class, 'setCompensationPlan'])
        ->whereNumber('loss')
        ->name('admin.losses.compensation');
    Route::post('/admin/kehilangan/{loss}/kompensasi/selesaikan', [AdminLossController::class, 'completeCompensation'])
        ->whereNumber('loss')
        ->name('admin.losses.compensation.complete');

    Route::get('/admin/aktivitas', [AdminActivityLogController::class, 'index'])
        ->name('admin.activity-logs.index');
    Route::get('/admin/aktivitas/{activityLog}', [AdminActivityLogController::class, 'show'])
        ->whereNumber('activityLog')
        ->name('admin.activity-logs.show');

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
        ->name('admin.dashboard');

    Route::get('/admin/peminjaman', [AdminBorrowingController::class, 'index'])
        ->name('admin.borrowings.index');

    Route::get('/admin/siswa', [AdminStudentController::class, 'index'])
        ->name('admin.students.index');

    Route::get('/admin/siswa/tambah', [AdminStudentController::class, 'create'])
        ->name('admin.students.create');

    Route::post('/admin/siswa', [AdminStudentController::class, 'store'])
        ->name('admin.students.store');

    Route::get('/admin/siswa/{student}/edit', [AdminStudentController::class, 'edit'])
        ->name('admin.students.edit');

    Route::put('/admin/siswa/{student}', [AdminStudentController::class, 'update'])
        ->name('admin.students.update');

    Route::patch('/admin/siswa/{student}/status', [AdminStudentController::class, 'toggleStatus'])
        ->name('admin.students.toggle-status');

    Route::get('/admin/siswa/impor', [AdminStudentImportController::class, 'index'])
        ->name('admin.students.import.index');
    Route::get('/admin/siswa/impor/template', [AdminStudentImportController::class, 'template'])
        ->name('admin.students.import.template');
    Route::post('/admin/siswa/impor/pratinjau', [AdminStudentImportController::class, 'preview'])
        ->name('admin.students.import.preview');
    Route::get('/admin/siswa/impor/{batch}/pratinjau', [AdminStudentImportController::class, 'showPreview'])
        ->name('admin.students.import.batch');
    Route::get('/admin/siswa/impor/{batch}/kesalahan', [AdminStudentImportController::class, 'downloadErrorReport'])
        ->name('admin.students.import.batch.errors');
    Route::post('/admin/siswa/impor/{batch}/proses', [AdminStudentImportController::class, 'import'])
        ->name('admin.students.import.batch.process');
    Route::post('/admin/siswa/impor/{batch}/batal', [AdminStudentImportController::class, 'cancel'])
        ->name('admin.students.import.batch.cancel');
    Route::get('/admin/siswa/impor/hasil', [AdminStudentImportController::class, 'result'])
        ->name('admin.students.import.result');
    Route::post('/admin/siswa/impor/hasil/tautan', [AdminStudentImportController::class, 'downloadActivationLinks'])
        ->name('admin.students.import.result.download');
    Route::post('/admin/siswa/{student}/tautan-aktivasi', [AdminStudentImportController::class, 'reissueActivationLink'])
        ->name('admin.students.activation.reissue');
    Route::post('/admin/siswa/{student}/reset-sandi', [PasswordRecoveryController::class, 'issueStudentRecoveryLink'])
        ->middleware('throttle:3,1')
        ->name('admin.students.password-reset-link');

    Route::get('/admin/inventaris', [AdminEquipmentController::class, 'index'])
        ->name('admin.equipment-units.index');

    Route::post('/admin/inventaris/alat', [AdminEquipmentController::class, 'storeEquipment'])
        ->name('admin.equipment.store');

    Route::post('/admin/inventaris/unit/{unit}/foto', [AdminEquipmentController::class, 'updateUnitPhoto'])
        ->name('admin.equipment-units.update-photo');

    Route::post('/admin/inventaris/alat/{equipment}/unit', [AdminEquipmentController::class, 'storeUnits'])
        ->name('admin.equipment-units.store');

    Route::post('/admin/inventaris/unit/{unit}/kondisi', [AdminEquipmentController::class, 'updateCondition'])
        ->name('admin.equipment-units.update-condition');

    Route::get('/admin/peminjaman/{borrowing}/periksa', [AdminDashboardController::class, 'review'])
        ->name('admin.borrowing.review');

    Route::post('/admin/peminjaman/{borrowing}/setujui', [AdminDashboardController::class, 'approve'])
        ->name('admin.borrowing.approve');

    Route::post('/admin/peminjaman/{borrowing}/tolak', [AdminDashboardController::class, 'reject'])
        ->name('admin.borrowing.reject');

    Route::get('/admin/peminjaman/{borrowing}/pengembalian', [ReturnController::class, 'show'])
        ->name('admin.borrowing.return.form');

    Route::post('/admin/peminjaman/{borrowing}/pengembalian', [ReturnController::class, 'store'])
        ->name('admin.borrowing.return.store');
});


// Backup management is restricted to Super Admin. Restoration is intentionally
// CLI-only so a destructive database restore cannot be triggered over HTTP.
Route::middleware(['auth', 'active', 'role:super_admin'])->group(function () {
    Route::get('/admin/cadangan', [AdminBackupController::class, 'index'])
        ->name('admin.backups.index');
    Route::post('/admin/cadangan', [AdminBackupController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('admin.backups.store');
    Route::post('/admin/cadangan/{filename}/verifikasi', [AdminBackupController::class, 'verify'])
        ->where('filename', '[A-Za-z0-9._-]+')
        ->middleware('throttle:10,1')
        ->name('admin.backups.verify');
    Route::get('/admin/cadangan/{filename}/unduh', [AdminBackupController::class, 'download'])
        ->where('filename', '[A-Za-z0-9._-]+')
        ->name('admin.backups.download');
});

// Only Super Admins may manage administrative staff accounts or create other Super Admins.
Route::middleware(['auth', 'active', 'role:super_admin'])->group(function () {
    Route::get('/admin/pengguna/staf', [AdminStaffController::class, 'index'])
        ->name('admin.staff.index');

    Route::get('/admin/pengguna/staf/tambah', [AdminStaffController::class, 'create'])
        ->name('admin.staff.create');

    Route::post('/admin/pengguna/staf', [AdminStaffController::class, 'store'])
        ->name('admin.staff.store');

    Route::get('/admin/pengguna/staf/{staff}/edit', [AdminStaffController::class, 'edit'])
        ->name('admin.staff.edit');

    Route::put('/admin/pengguna/staf/{staff}', [AdminStaffController::class, 'update'])
        ->name('admin.staff.update');

    Route::patch('/admin/pengguna/staf/{staff}/status', [AdminStaffController::class, 'toggleStatus'])
        ->name('admin.staff.toggle-status');
    Route::post('/admin/pengguna/staf/{staff}/reset-sandi', [PasswordRecoveryController::class, 'issuePetugasRecoveryLink'])
        ->middleware('throttle:3,1')
        ->name('admin.staff.password-reset-link');
});
