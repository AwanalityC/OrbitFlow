<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loss extends Model
{
    protected $fillable = [
        'borrowing_id',
        'equipment_unit_id',
        'description',
        'status',
        'reported_at',
        'evidence_photo_path',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'Dilaporkan' => 'Menunggu pemeriksaan',
            'Hilang Permanen' => 'Hilang permanen',
            'DitemukanKembali' => 'Ditemukan kembali',
            default => $this->status,
        };
    }

    public function borrowing()
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function equipmentUnit()
    {
        return $this->belongsTo(EquipmentUnit::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function compensation()
    {
        return $this->hasOne(Compensation::class);
    }
}
