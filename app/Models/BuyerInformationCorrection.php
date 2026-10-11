<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerInformationCorrection extends Model
{
    public const CONTACT_LABELS = [
        'contact_number' => 'Contact number',
        'address' => 'Current address',
        'notes' => 'Additional contact details',
    ];

    protected $fillable = [];

    protected $hidden = ['original_values', 'proposed_values', 'reason', 'review_notes'];

    protected function casts(): array
    {
        return ['original_values' => 'array', 'proposed_values' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
