<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $fillable = [
        'name',
        'category',
        'description',
        'image_path',
    ];

    public function units()
    {
        return $this->hasMany(EquipmentUnit::class);
    }

    public function borrowableUnits()
    {
        return $this->hasMany(EquipmentUnit::class)->availableForBorrowing();
    }

    public function borrowingItems()
    {
        return $this->hasMany(BorrowingItem::class);
    }

    /**
     * Return the best currently documented physical-unit photo for the catalogue.
     * Baik is preferred, followed by Rusak Ringan, then other known conditions.
     * The equipment-level image is a fallback for older or newly registered units.
     */
    public function catalogPhotoPath(): ?string
    {
        $units = $this->relationLoaded('units')
            ? $this->getRelation('units')
            : $this->units()->get();

        $conditionPriority = [
            'Baik' => 0,
            'Rusak Ringan' => 1,
            'Rusak Berat' => 2,
            'Hilang' => 3,
        ];
        $statusPriority = [
            'Tersedia' => 0,
            'Dipinjam' => 1,
            'Tidak Tersedia' => 2,
            'Hilang' => 3,
        ];

        $unitWithPhoto = $units
            ->filter(fn ($unit) => filled($unit->current_photo_path) && $unit->condition !== 'Hilang')
            ->sort(function ($left, $right) use ($conditionPriority, $statusPriority) {
                $conditionOrder = ($conditionPriority[$left->condition] ?? 99)
                    <=> ($conditionPriority[$right->condition] ?? 99);

                if ($conditionOrder !== 0) {
                    return $conditionOrder;
                }

                $statusOrder = ($statusPriority[$left->status] ?? 99)
                    <=> ($statusPriority[$right->status] ?? 99);

                if ($statusOrder !== 0) {
                    return $statusOrder;
                }

                return ($right->updated_at?->getTimestamp() ?? 0)
                    <=> ($left->updated_at?->getTimestamp() ?? 0);
            })
            ->first();

        return $unitWithPhoto?->current_photo_path ?: $this->image_path;
    }

    public function catalogPhotoUrl(): ?string
    {
        $path = $this->catalogPhotoPath();

        return $path ? url('/storage/' . ltrim($path, '/')) : null;
    }

    public function catalogPhotoCondition(): ?string
    {
        $path = $this->catalogPhotoPath();
        if (! $path) {
            return null;
        }

        $units = $this->relationLoaded('units')
            ? $this->getRelation('units')
            : $this->units()->get();

        $conditionPriority = ['Baik' => 0, 'Rusak Ringan' => 1, 'Rusak Berat' => 2, 'Hilang' => 3];

        return $units
            ->filter(fn ($unit) => $unit->current_photo_path === $path)
            ->sortBy(fn ($unit) => $conditionPriority[$unit->condition] ?? 99)
            ->first()?->condition;
    }
}