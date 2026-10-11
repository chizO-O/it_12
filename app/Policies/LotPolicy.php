<?php

namespace App\Policies;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\User;

class LotPolicy
{
    public function view(User $user, Lot $lot): bool
    {
        return in_array($user->role, [UserRole::Buyer, UserRole::Agent, UserRole::Admin, UserRole::UnitManager], true);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::FieldPlanner;
    }

    public function update(User $user, Lot $lot): bool
    {
        return $user->role === UserRole::FieldPlanner && $lot->status === LotStatus::Available;
    }

    public function delete(User $user, Lot $lot): bool
    {
        return $this->update($user, $lot);
    }
}
