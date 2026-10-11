<?php

namespace Tests\Feature;

use App\Enums\CtsStatus;
use App\Enums\LotStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\CtsRecord;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function completedCts(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $otherBuyer = User::factory()->create(['role' => UserRole::Buyer]);
        $lot = Lot::factory()->create(['status' => LotStatus::Reserved, 'assigned_agent_id' => $agent->id]);
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);
        $reservation = new Reservation;
        $reservation->buyer_id = $buyer->id;
        $reservation->agent_id = $agent->id;
        $reservation->lot_id = $lot->id;
        $reservation->inquiry_id = $inquiry->id;
        $reservation->status = ReservationStatus::Approved;
        $reservation->reviewed_at = now();
        $reservation->reviewed_by = $manager->id;
        $reservation->save();
        $transaction = Transaction::create(['reservation_id' => $reservation->id, 'status' => 'cts_completed']);
        $cts = CtsRecord::create([
            'transaction_id' => $transaction->id, 'initiated_by_id' => $manager->id,
            'status' => CtsStatus::Completed, 'completed_at' => now(),
        ]);

        return compact('buyer', 'manager', 'agent', 'cashier', 'otherBuyer', 'lot', 'reservation', 'transaction', 'cts');
    }

    private function confirm(User $manager, Transaction $transaction, array $override = []): void
    {
        $this->actingAs($manager)->post(route('sales.confirm', $transaction), array_replace([
            'agreed_price' => '500000.00', 'payment_type' => 'installment',
            'down_payment' => '100000.00', 'term_months' => 3,
            'first_due_date' => now()->addMonthNoOverflow()->toDateString(),
            'terms_confirmed' => 1,
        ], $override))->assertRedirect(route('sales.index'));
    }

    public function test_only_responsible_manager_can_confirm_after_completed_cts_and_payment_schedule_sums_exactly(): void
    {
        extract($this->completedCts());
        $this->actingAs($buyer)->post(route('sales.confirm', $transaction), [])->assertForbidden();
        $otherManager = User::factory()->create(['role' => UserRole::UnitManager]);
        $this->actingAs($otherManager)->post(route('sales.confirm', $transaction), [])->assertForbidden();
        $cts->status = CtsStatus::ForSigning;
        $cts->save();
        $this->actingAs($manager)->post(route('sales.confirm', $transaction), [])->assertForbidden();
        $cts->status = CtsStatus::Completed;
        $cts->save();
        $this->confirm($manager, $transaction);
        $this->assertSame('sold', $transaction->fresh()->status);
        $this->assertSame(LotStatus::Sold, $lot->fresh()->status);
        $this->assertDatabaseCount('payment_schedules', 4);
        $this->assertSame('500000.00', number_format($transaction->paymentSchedules->sum('amount_due'), 2, '.', ''));
        $this->actingAs($manager)->post(route('sales.confirm', $transaction), [])->assertForbidden();
    }

    public function test_sale_price_must_match_executed_contract_price_when_provided(): void
    {
        extract($this->completedCts());
        $cts->contract_price = '500000.00';
        $cts->save();
        $this->actingAs($manager)->post(route('sales.confirm', $transaction), [
            'agreed_price' => '450000.00', 'payment_type' => 'spot_cash',
            'terms_confirmed' => 1,
        ])->assertSessionHasErrors('agreed_price');
        $this->assertSame('cts_completed', $transaction->fresh()->status);
        $this->assertSame(LotStatus::Reserved, $lot->fresh()->status);
        $this->confirm($manager, $transaction);
        $this->assertSame('sold', $transaction->fresh()->status);
    }

    public function test_cashier_verifies_real_payment_and_full_balance_updates_lot(): void
    {
        extract($this->completedCts());
        $this->confirm($manager, $transaction, ['payment_type' => 'spot_cash', 'down_payment' => null, 'term_months' => null, 'first_due_date' => null]);
        $this->actingAs($buyer)->post(route('sales.payments.store', $transaction), [])->assertForbidden();
        $this->actingAs($cashier)->post(route('sales.payments.store', $transaction), [
            'amount' => '200000.00', 'payment_date' => today()->toDateString(), 'reference_number' => 'SGRE-0001',
            'method' => 'cash', 'physically_received' => 1,
        ])->assertRedirect();
        $this->assertSame('sold', $transaction->fresh()->status);
        $this->assertSame(LotStatus::Sold, $lot->fresh()->status);
        // The verified receipt notification must exist immediately, not depend on a scheduler.
        $this->assertSame(2, $buyer->notifications()->count()); // sale confirmation + receipt
        $this->assertSame(2, $agent->notifications()->count());
        $this->assertSame(2, $manager->notifications()->count());
        // SQLite timestamps may be identical for sale and payment alerts;
        // don't assume latest() uniquely orders notifications from one second.
        $receiptAlerts = $buyer->notifications()->get()->filter(
            fn ($notification) => str_contains(
                (string) ($notification->data['description'] ?? ''),
                'SGRE-0001'
            )
        );
        $this->assertCount(1, $receiptAlerts);
        $this->actingAs($cashier)->post(route('sales.payments.store', $transaction), [
            'amount' => '400000.00', 'payment_date' => today()->toDateString(), 'reference_number' => 'SGRE-0002',
            'method' => 'cash', 'physically_received' => 1,
        ])->assertSessionHasErrors('amount');
        $this->assertSame(2, $buyer->notifications()->count());
        $this->actingAs($cashier)->post(route('sales.payments.store', $transaction), [
            'amount' => '300000.00', 'payment_date' => today()->toDateString(), 'reference_number' => 'SGRE-0002',
            'method' => 'bank_transfer', 'physically_received' => 1,
        ])->assertRedirect();
        $this->assertSame('fully_paid', $transaction->fresh()->status);
        $this->assertSame(LotStatus::FullyPaid, $lot->fresh()->status);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(3, $buyer->notifications()->count());
        $this->assertSame(3, $agent->notifications()->count());
        $this->assertSame(3, $manager->notifications()->count());
        $this->assertEqualsWithDelta(500000, (float) $transaction->payments()->sum('amount'), 0.001);
    }

    public function test_payment_reference_is_unique_and_buyer_can_only_view_their_sale(): void
    {
        extract($this->completedCts());
        $this->confirm($manager, $transaction);
        $payload = [
            'amount' => '100.00', 'payment_date' => today()->toDateString(), 'reference_number' => 'SGRE-REF-001',
            'method' => 'cash', 'physically_received' => 1,
        ];
        $this->actingAs($cashier)->post(route('sales.payments.store', $transaction), $payload)->assertRedirect();
        $this->post(route('sales.payments.store', $transaction), $payload)->assertSessionHasErrors('reference_number');
        $this->assertDatabaseCount('payments', 1);
        $this->actingAs($buyer)->get(route('sales.show', $transaction))->assertOk()
            ->assertSee('499,900.00')->assertDontSee('Record & Verify Payment');
        $this->actingAs($otherBuyer)->get(route('sales.show', $transaction))->assertForbidden();
        $planner = User::factory()->create(['role' => UserRole::FieldPlanner]);
        $this->actingAs($planner)->get(route('sales.index'))->assertForbidden();
    }
}
