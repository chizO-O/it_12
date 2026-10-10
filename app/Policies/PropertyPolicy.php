<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function view(User $user, Property $property): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::FieldPlanner;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::FieldPlanner;
    }

    public function update(User $user, Property $property): bool
    {
        return $user->role === UserRole::FieldPlanner;
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->role === UserRole::FieldPlanner;
    }
}
