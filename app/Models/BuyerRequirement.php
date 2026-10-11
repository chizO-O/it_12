<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerRequirement extends Model
{
    protected $fillable = ['contact_number', 'address', 'notes'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
