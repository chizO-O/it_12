<?php

namespace App\Models;

use App\Enums\LotStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'available'];

    protected $fillable = [
        'property_id',
        'lot_no',
        'block_no',
        'area_sqm',
        'selling_price',
        'grid_row',
        'grid_column',
        'status',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'status' => LotStatus::class,
            'grid_row' => 'integer',
            'grid_column' => 'integer',
            'agent_assigned_at' => 'datetime',
        ];
    }
}
