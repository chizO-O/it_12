<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'address',
        'total_area_sqm',
        'description',
    ];

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    protected function casts(): array
    {
        return ['total_area_sqm' => 'decimal:2'];
    }
}
