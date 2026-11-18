<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Borrowing extends Model
{
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'purpose',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(BorrowingItem::class);
    }

    public function returnRecords()
    {
        return $this->hasMany(ReturnRecord::class);
    }

    public function losses()
    {
        return $this->hasMany(Loss::class);
    }
}