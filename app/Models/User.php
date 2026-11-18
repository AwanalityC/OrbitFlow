<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @use HasFactory<UserFactory>
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_PETUGAS = 'petugas';
    public const ROLE_SISWA = 'siswa';

    // Treat newly instantiated users as active unless explicitly deactivated.
    // The database also defaults this field to true; this model default keeps
    // freshly created in-memory models consistent before they are reloaded.
    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'nis',
        'class_name',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        // `admin` remains recognized for compatibility with legacy test data
        // and any account that has not yet received the account-role migration.
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->role === self::ROLE_PETUGAS;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_SISWA;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN, 'admin' => 'Super Admin',
            self::ROLE_PETUGAS => 'Petugas Inventaris',
            self::ROLE_SISWA => 'Siswa',
            default => 'Pengguna',
        };
    }

    public function borrowings()
    {
        return $this->hasMany(Borrowing::class);
    }

    public function studentAccountActivations()
    {
        return $this->hasMany(StudentAccountActivation::class);
    }

    public function compensations()
    {
        return $this->hasMany(Compensation::class);
    }
}
