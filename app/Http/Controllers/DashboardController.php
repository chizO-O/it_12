<?php

namespace App\Http\Controllers;

use App\Enums\CtsStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CtsRecord;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->dashboardRoute());
    }

    public function show(Request $request): View
    {
        $role = $request->user()->role;

        $user = $request->user();
        $metrics = match ($role) {
            UserRole::Buyer => [
                'My reservations' => Reservation::where('buyer_id', $user->id)->count(),
                'My CTS documents' => CtsRecord::whereHas('transaction.reservation', fn ($q) => $q->where('buyer_id', $user->id))->count(),
                'Unread notifications' => $user->unreadNotifications()->count(),
            ],
            UserRole::Agent => [
                'Assigned lots' => Lot::where('assigned_agent_id', $user->id)->count(),
                'My conversations' => Inquiry::where('agent_id', $user->id)->count(),
                'My reservations' => Reservation::where('agent_id', $user->id)->count(),
                'Unread notifications' => $user->unreadNotifications()->count(),
            ],
            UserRole::UnitManager => [
                'My Agents' => User::where('role', UserRole::Agent->value)->where('unit_manager_id', $user->id)->count(),
                'Available unassigned lots' => Lot::where('status', \App\Enums\LotStatus::Available->value)->whereNull('assigned_agent_id')->count(),
                'Pending reviews' => Reservation::where('status', ReservationStatus::Pending->value)
                    ->whereHas('agent', fn ($q) => $q->where('unit_manager_id', $user->id))->count(),
                'Unread notifications' => $user->unreadNotifications()->count(),
            ],
            UserRole::FieldPlanner => [
                'Properties managed' => Property::count(),
                'Property lots' => Lot::count(),
                'CTS awaiting verification' => CtsRecord::where('status', CtsStatus::Requested->value)->count(),
                'Unread notifications' => $user->unreadNotifications()->count(),
            ],
            UserRole::Admin => [
                'Office employees' => User::whereIn('role', [UserRole::Admin->value, UserRole::UnitManager->value, UserRole::FieldPlanner->value, UserRole::Cashier->value])->count(),
                'CTS awaiting draft' => CtsRecord::where('status', CtsStatus::PropertyVerified->value)->count(),
                'Unread notifications' => $user->unreadNotifications()->count(),
            ],
            UserRole::Cashier => [
                'Sales with balance' => Transaction::where('status', 'sold')->count(),
                'Payments recorded today' => Payment::whereDate('payment_date', today('Asia/Manila')->toDateString())->count(),
                'Unread notifications' => $user->unreadNotifications()->count(),
            ],
        };

        return view('dashboard', [
            'workspace' => $role->workspaceLabel(),
            'modules' => config('workspace.navigation.'.$role->value, []),
            'metrics' => $metrics,
        ]);
    }
}
