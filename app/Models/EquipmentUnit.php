<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EquipmentUnit extends Model
{
    protected $fillable = [
        'equipment_id',
        'unit_code',
        'condition',
        'status',
        'current_photo_path',
    ];

    /**
     * Units that can actually be allocated to a new borrowing request.
     * The pivot claim is checked as a second source of truth in case a unit's
     * status was accidentally changed while an active loan still references it.
     */
    public function scopeAvailableForBorrowing(Builder $query): Builder
    {
        return $query
            ->where('status', 'Tersedia')
            ->whereIn('condition', ['Baik', 'Rusak Ringan'])
            ->whereNotExists(function ($activeAllocations) {
                $activeAllocations->selectRaw('1')
                    ->from('borrowing_item_equipment_unit as active_allocations')
                    ->whereColumn(
                        'active_allocations.active_equipment_unit_id',
                        'equipment_units.id'
                    );
            });
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function losses()
    {
        return $this->hasMany(Loss::class);
    }

    public function returnRecords()
    {
        return $this->hasMany(ReturnRecord::class);
    }
}