<?php

namespace App\Policies;

use App\Enums\CtsStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CtsRecord;
use App\Models\Reservation;
use App\Models\User;

class CtsRecordPolicy
{
    public function initiate(User $user, Reservation $reservation): bool
    {
        return $user->role === UserRole::UnitManager
            && $reservation->agent?->unit_manager_id === $user->id
            && $reservation->status === ReservationStatus::Approved;
    }

    public function view(User $user, CtsRecord $cts): bool
    {
        $reservation = $cts->transaction->reservation;

        return $user->role === UserRole::Admin
            || $user->role === UserRole::FieldPlanner
            || ($user->role === UserRole::Buyer && $reservation->buyer_id === $user->id)
            || ($user->role === UserRole::Agent && $reservation->agent_id === $user->id)
            || ($user->role === UserRole::UnitManager && $reservation->agent?->unit_manager_id === $user->id);
    }

    public function viewDocument(User $user, CtsRecord $cts): bool
    {
        return $this->view($user, $cts) && $user->role !== UserRole::FieldPlanner;
    }

    public function verifyProperty(User $user, CtsRecord $cts): bool
    {
        return $user->role === UserRole::FieldPlanner && $cts->status === CtsStatus::Requested;
    }

    public function uploadDraft(User $user, CtsRecord $cts): bool
    {
        return $user->role === UserRole::Admin
            && in_array($cts->status, [CtsStatus::PropertyVerified, CtsStatus::RevisionRequested], true);
    }

    public function review(User $user, CtsRecord $cts): bool
    {
        return $user->role === UserRole::Buyer
            && $cts->transaction->reservation->buyer_id === $user->id
            && $cts->status === CtsStatus::ForBuyerReview;
    }

    public function complete(User $user, CtsRecord $cts): bool
    {
        return $user->role === UserRole::Admin && $cts->status === CtsStatus::ForSigning;
    }
}
