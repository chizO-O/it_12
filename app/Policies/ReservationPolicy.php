<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function updateContact(User $user, Reservation $reservation): bool
    {
        return $this->isBuyer($user, $reservation) && $reservation->status === ReservationStatus::Pending;
    }

    public function viewAssessment(User $user, Reservation $reservation): bool
    {
        return $this->isAssignedAgent($user, $reservation) || $this->reviewCorrection($user, $reservation);
    }

    public function viewAssessmentFacts(User $user, Reservation $reservation): bool
    {
        return $this->isBuyer($user, $reservation) || $this->viewAssessment($user, $reservation);
    }

    public function updateAssessment(User $user, Reservation $reservation): bool
    {
        return $this->isAssignedAgent($user, $reservation)
            && in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
            && $reservation->transaction?->ctsRecord?->status !== \App\Enums\CtsStatus::Completed;
    }

    public function requestCorrection(User $user, Reservation $reservation): bool
    {
        if ($reservation->transaction?->ctsRecord?->status === \App\Enums\CtsStatus::Completed) {
            return false;
        }

        return $this->isBuyer($user, $reservation)
            || ($this->isAssignedAgent($user, $reservation) && $reservation->status !== ReservationStatus::Pending);
    }

    public function reviewCorrection(User $user, Reservation $reservation): bool
    {
        return $user->role === UserRole::UnitManager && $reservation->agent?->unit_manager_id === $user->id;
    }

    private function isBuyer(User $user, Reservation $reservation): bool
    {
        return $user->role === UserRole::Buyer && $reservation->buyer_id === $user->id;
    }

    private function isAssignedAgent(User $user, Reservation $reservation): bool
    {
        return $user->role === UserRole::Agent && $reservation->agent_id === $user->id
            && $reservation->inquiry?->agent_id === $user->id && $reservation->lot?->assigned_agent_id === $user->id;
    }
}
