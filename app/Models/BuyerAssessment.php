<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerAssessment extends Model
{
    public const FACT_LABELS = [
        'employment_status' => 'Employment status',
        'source_of_income' => 'Source of income',
        'monthly_income_range' => 'Approximate monthly income range',
        'preferred_payment_method' => 'Preferred payment method',
        'requested_installment_months' => 'Requested installment duration (months)',
        'proposed_down_payment' => 'Proposed down payment (PHP)',
        'additional_clarifications' => 'Additional requirements or clarifications',
    ];

    protected $fillable = [
        'employment_status', 'source_of_income', 'monthly_income_range',
        'preferred_payment_method', 'requested_installment_months',
        'proposed_down_payment', 'additional_clarifications', 'agent_remarks',
    ];

    protected $hidden = ['agent_remarks'];

    protected function casts(): array
    {
        return [
            'requested_installment_months' => 'integer',
            'proposed_down_payment' => 'decimal:2',
            'privacy_notice_given_at' => 'datetime',
        ];
    }

    public function facts(): array
    {
        return collect(array_keys(self::FACT_LABELS))->mapWithKeys(fn ($field) => [$field => $this->getAttribute($field)])->all();
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
