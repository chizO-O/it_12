<?php

namespace App\Models;

use App\Enums\CtsStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtsRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CtsStatus::class,
            'property_verified_at' => 'datetime',
            'draft_uploaded_at' => 'datetime',
            'buyer_reviewed_at' => 'datetime',
            'executed_uploaded_at' => 'datetime',
            'notarized_at' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }
}
