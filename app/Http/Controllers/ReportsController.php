<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\PesoAmount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Admin, UserRole::UnitManager], true), 403);

        $showLots = true;
        $showSales = true;
        $teamAgentIds = $user->role === UserRole::UnitManager
            ? User::query()->where('role', UserRole::Agent->value)->where('unit_manager_id', $user->id)->pluck('id')
            : collect();

        $lotCounts = collect();
        if ($showLots) {
            $lots = Lot::query();
            if ($user->role === UserRole::UnitManager) {
                $lots->whereIn('assigned_agent_id', $teamAgentIds);
            }
            $lotCounts = $lots->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        }

        $totalSalesCents = 0;
        $totalCollectionsCents = 0;
        $outstandingCents = 0;
        $transactionsCount = 0;
        $monthly = [];
        if ($showSales) {
            $transactions = Transaction::query()->whereNotNull('terms_confirmed_at');
            if ($user->role === UserRole::UnitManager) {
                $transactions->whereHas('reservation', fn ($q) => $q->whereIn('agent_id', $teamAgentIds));
            }
            $transactionsCount = (clone $transactions)->count();
            $totalSalesCents = PesoAmount::cents((clone $transactions)->sum('agreed_price'));

            $payments = Payment::query()->whereHas('transaction', function ($query) use ($user, $teamAgentIds) {
                $query->whereNotNull('terms_confirmed_at');
                if ($user->role === UserRole::UnitManager) {
                    $query->whereHas('reservation', fn ($q) => $q->whereIn('agent_id', $teamAgentIds));
                }
            });
            $totalCollectionsCents = PesoAmount::cents((clone $payments)->sum('amount'));
            $outstandingCents = max(0, $totalSalesCents - $totalCollectionsCents);
            $start = CarbonImmutable::today('Asia/Manila')->startOfMonth();
            for ($offset = 5; $offset >= 0; $offset--) {
                $month = $start->subMonths($offset);
                $end = $month->endOfMonth();
                $monthly[] = [
                    'label' => $month->format('M Y'),
                    'amount' => PesoAmount::cents((clone $payments)->whereDate('payment_date', '>=', $month->toDateString())
                        ->whereDate('payment_date', '<=', $end->toDateString())->sum('amount')),
                ];
            }
        }

        return view('reports.index', compact('showLots', 'showSales', 'lotCounts', 'totalSalesCents', 'totalCollectionsCents', 'outstandingCents', 'transactionsCount', 'monthly'));
    }
}
