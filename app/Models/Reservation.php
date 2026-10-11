<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function buyerRequirements(): HasMany
    {
        return $this->hasMany(BuyerRequirement::class);
    }

    public function buyerForm(): HasOne
    {
        return $this->hasOne(BuyerRequirement::class)->where('requirement_type', 'buyer_form');
    }

    public function buyerAssessment(): HasOne
    {
        return $this->hasOne(BuyerAssessment::class);
    }

    public function informationCorrections(): HasMany
    {
        return $this->hasMany(BuyerInformationCorrection::class);
    }
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

}
