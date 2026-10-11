<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'buyer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function buyerInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'buyer_id');
    }

    public function assignedInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'agent_id');
    }

    public function buyerReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'buyer_id');
    }

    public function agentReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'agent_id');
    }

    public function supervisedAgents(): HasMany
    {
        return $this->hasMany(self::class, 'unit_manager_id');
    }

    public function assignedLots(): HasMany
    {
        return $this->hasMany(Lot::class, 'assigned_agent_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
