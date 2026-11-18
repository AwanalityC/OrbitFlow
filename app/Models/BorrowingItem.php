<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BorrowingItem extends Model
{
    protected $fillable = [
        'borrowing_id',
        'equipment_id',
        'quantity',
    ];

    public function borrowing()
    {
        return $this->belongsTo(Borrowing::class);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    /** All physical units ever allocated to this borrowing item. */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(
            EquipmentUnit::class,
            'borrowing_item_equipment_unit',
            'borrowing_item_id',
            'equipment_unit_id'
        )->withPivot(['borrowed_at', 'returned_at', 'condition_at_borrowing', 'active_equipment_unit_id', 'handover_photo_path', 'handover_photo_taken_by'])->withTimestamps();
    }

    /** Units allocated to this item that have not yet been returned. */
    public function activeUnits(): BelongsToMany
    {
        return $this->units()
            ->wherePivotNull('returned_at')
            ->wherePivotNotNull('active_equipment_unit_id');
    }
}
