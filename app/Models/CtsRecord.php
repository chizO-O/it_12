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
            'contract_price' => 'decimal:2',
            'contract_prepared_at' => 'datetime',
            'buyer_details_submitted_at' => 'datetime',
            'buyer_age' => 'integer',
            'landowner_age' => 'integer',
        ];
    }

    /** Both CTS parties must submit their independently verified contract particulars. */
    public function partiesReady(): bool
    {
        return (bool) ($this->buyer_details_submitted_at
            && filled($this->buyer_full_name)
            && filled($this->buyer_permanent_address)
            && filled($this->buyer_marital_status)
            && $this->buyer_age !== null
            && filled($this->buyer_nationality)
            && $this->property_verified_at
            && filled($this->landowner_name)
            && filled($this->landowner_contact)
            && filled($this->landowner_address)
            && filled($this->landowner_permanent_address)
            && filled($this->landowner_marital_status)
            && $this->landowner_age !== null
            && filled($this->landowner_nationality));
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
