<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_name',
        'actor_email',
        'actor_role',
        'category',
        'event_key',
        'summary',
        'subject_type',
        'subject_id',
        'subject_label',
        'before_data',
        'after_data',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'borrowing' => 'Peminjaman',
            'returns' => 'Pengembalian',
            'inventory' => 'Inventaris',
            'accounts' => 'Akun',
            'imports' => 'Impor data',
            'security' => 'Keamanan',
            'backup' => 'Cadangan & Pemulihan',
            default => 'Lainnya',
        };
    }

    public function getActorRoleLabelAttribute(): string
    {
        return match ($this->actor_role) {
            'super_admin', 'admin' => 'Super Admin',
            'petugas' => 'Petugas',
            'siswa' => 'Siswa',
            default => 'Pengguna',
        };
    }

    public function getEventLabelAttribute(): string
    {
        return match ($this->event_key) {
            'borrowing.approved' => 'Pengajuan disetujui',
            'borrowing.rejected' => 'Pengajuan ditolak',
            'return.processed' => 'Pengembalian diproses',
            'equipment.created' => 'Alat baru dibuat',
            'equipment.units_added' => 'Unit ditambahkan',
            'inventory.condition_updated' => 'Kondisi unit diubah',
            'student.created' => 'Akun siswa dibuat',
            'student.updated' => 'Data siswa diperbarui',
            'student.status_changed' => 'Status akun siswa diubah',
            'staff.created' => 'Akun staf dibuat',
            'staff.updated' => 'Data staf diperbarui',
            'staff.status_changed' => 'Status akun staf diubah',
            'students.imported' => 'Impor siswa selesai',
            'student.activation_links_downloaded' => 'Tautan aktivasi diunduh',
            'student.activation_link_reissued' => 'Tautan aktivasi diterbitkan ulang',
            'password_recovery.admin_link_issued' => 'Tautan pemulihan diterbitkan',
            'student.activated' => 'Akun siswa diaktifkan',
            'password.reset.completed' => 'Kata sandi berhasil dipulihkan',
            'auth.login.succeeded' => 'Login berhasil',
            'auth.login.failed' => 'Percobaan login gagal',
            'auth.login.throttled' => 'Percobaan login dibatasi',
            'auth.logout' => 'Logout berhasil',
            'backup.created' => 'Backup dibuat',
            'backup.failed' => 'Pembuatan backup gagal',
            'backup.verified' => 'Backup diverifikasi',
            'backup.verification_failed' => 'Verifikasi backup gagal',
            'backup.downloaded' => 'Backup diunduh',
            'backup.restored' => 'Backup dipulihkan',
            default => str_replace('.', ' · ', ucfirst($this->event_key)),
        };
    }
}
