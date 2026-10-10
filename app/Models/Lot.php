<?php

namespace App\Models;

use App\Enums\LotStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'status',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    protected function casts(): array
    {
        return [
            'area_sqm' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'status' => LotStatus::class,
        ];
    }
}
