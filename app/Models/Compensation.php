<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compensation extends Model
{
    protected $table = 'compensations';

    protected $fillable = [
        'user_id',
        'return_record_id',
        'loss_id',
        'type',
        'description',
        'status',
        'resolution_type',
        'amount',
        'resolution_notes',
        'recovered_condition',
        'decided_by',
        'decided_at',
        'resolved_by',
        'resolved_at',
        'completion_notes',
        'replacement_equipment_unit_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function returnRecord()
    {
        return $this->belongsTo(ReturnRecord::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function loss()
    {
        return $this->belongsTo(Loss::class);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function replacementEquipmentUnit()
    {
        return $this->belongsTo(EquipmentUnit::class, 'replacement_equipment_unit_id');
    }
}