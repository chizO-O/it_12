<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['agreed_price' => 'decimal:2'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function ctsRecord(): HasOne
    {
        return $this->hasOne(CtsRecord::class);
    }
}
