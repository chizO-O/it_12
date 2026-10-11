<?php

namespace App\Policies;

use App\Enums\CtsStatus;
use App\Enums\LotStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        $reservation = $transaction->reservation;

        // Buyer and Cashier may view payment history; managers have only the
        // unconfirmed sale they must authorize, never ongoing payment status.
        return $user->role === UserRole::Cashier
            || ($user->role === UserRole::Buyer && $reservation->buyer_id === $user->id)
            || ($user->role === UserRole::UnitManager
                && $reservation->agent?->unit_manager_id === $user->id
                && $transaction->status === 'cts_completed'
                && $transaction->terms_confirmed_at === null);
    }

    public function confirmTerms(User $user, Transaction $transaction): bool
    {
        return $user->role === UserRole::UnitManager
            && $transaction->reservation->agent?->unit_manager_id === $user->id
            && $transaction->reservation->status === ReservationStatus::Approved
            && $transaction->ctsRecord?->status === CtsStatus::Completed
            && $transaction->reservation->lot?->status === LotStatus::Reserved
            && $transaction->status === 'cts_completed';
    }

    public function recordPayment(User $user, Transaction $transaction): bool
    {
        return $user->role === UserRole::Cashier
            && $transaction->terms_confirmed_at !== null
            && $transaction->status === 'sold';
    }
}
