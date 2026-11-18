<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRecord extends Model
{
    protected $fillable = [
        'borrowing_id',
        'equipment_unit_id',
        'condition',
        'notes',
        'photo_path',
        'photo_taken_by',
        'returned_at',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
    ];

    public function borrowing()
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function equipmentUnit()
    {
        return $this->belongsTo(EquipmentUnit::class);
    }

    public function photoTakenBy()
    {
        return $this->belongsTo(User::class, 'photo_taken_by');
    }

    public function compensation()
    {
        return $this->hasOne(Compensation::class);
    }
}
