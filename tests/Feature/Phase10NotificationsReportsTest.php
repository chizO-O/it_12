<?php

namespace Tests\Feature;

use App\Enums\LotStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SaleActivity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase10NotificationsReportsTest extends TestCase
{
    use RefreshDatabase;

    private function sale(User $buyer, User $agent, string $price = '500.00'): Transaction
    {
        $lot = Lot::factory()->create(['status' => LotStatus::Sold, 'assigned_agent_id' => $agent->id]);
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);
        $reservation = new Reservation;
        $reservation->buyer_id = $buyer->id;
        $reservation->agent_id = $agent->id;
        $reservation->lot_id = $lot->id;
        $reservation->inquiry_id = $inquiry->id;
        $reservation->status = ReservationStatus::Approved;
        $reservation->reviewed_at = now();
        $reservation->save();

        return Transaction::create([
            'reservation_id' => $reservation->id,
            'status' => 'sold',
            'payment_type' => 'installment',
            'agreed_price' => $price,
            'down_payment' => '0.00',
            'term_months' => 1,
            'terms_confirmed_at' => now(),
        ]);
    }

    public function test_notification_center_requires_login_and_only_opens_own_notification(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $transaction = $this->sale($buyer, $agent);
        $buyer->notify(new SaleActivity($transaction->id, 'Your verified payment is ready to view.'));
        $notification = $buyer->notifications()->firstOrFail();

        $this->get(route('notifications.index'))->assertRedirect();
        $this->actingAs($other)->post(route('notifications.open', $notification->id))->assertNotFound();
        $this->actingAs($other)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertNull($notification->fresh()->read_at);
        $this->actingAs($buyer)->get(route('notifications.index'))->assertOk()->assertSee('Your verified payment is ready to view.');
        $this->actingAs($buyer)->post(route('notifications.open', $notification->id))
            ->assertRedirect(route('sales.show', $transaction));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notifications_display_philippine_time_without_changing_saved_utc_timestamps(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $transaction = $this->sale($buyer, $agent);
        $buyer->notify(new SaleActivity($transaction->id, 'Sample notification timestamp.'));
        $item = $buyer->notifications()->firstOrFail();
        $item->created_at = \Carbon\CarbonImmutable::parse('2026-10-11 10:46:00', 'UTC');
        $item->save();
        $this->actingAs($buyer)->get(route('notifications.index'))
            ->assertOk()->assertSee('Oct 11, 2026 6:46 PM PHT')
            ->assertDontSee('Oct 11, 2026 10:46 AM');
    }

    public function test_reports_enforce_role_boundaries_and_supervised_agent_scope(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $anotherManager = User::factory()->create(['role' => UserRole::UnitManager]);
        $anotherAgent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $anotherManager->id]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $planner = User::factory()->create(['role' => UserRole::FieldPlanner]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $ownSale = $this->sale($buyer, $agent, '500.00');
        $this->sale($buyer, $anotherAgent, '750.00');
        Payment::create([
            'transaction_id' => $ownSale->id,
            'amount' => '125.00',
            'payment_date' => today()->toDateString(),
            'reference_number' => 'TEST-RECEIPT-123',
            'method' => 'cash',
            'recorded_by_id' => $cashier->id,
            'verified_by_id' => $cashier->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($buyer)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.index'))->assertOk()
            ->assertSee('₱500.00')->assertSee('₱125.00')->assertDontSee('₱1,250.00');
        $this->actingAs($anotherManager)->get(route('reports.index'))->assertOk()
            ->assertSee('₱750.00')->assertDontSee('₱500.00');
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()->assertSee('₱1,250.00');
        $this->actingAs($planner)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.index'))->assertForbidden();
    }

    public function test_scheduled_payment_reminders_are_idempotent_and_skip_paid_installments(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $transaction = $this->sale($buyer, $agent);
        $today = CarbonImmutable::today('Asia/Manila');
        $schedule = PaymentSchedule::create([
            'transaction_id' => $transaction->id,
            'installment_no' => 1,
            'due_date' => $today->addDay()->toDateString(),
            'amount_due' => '500.00',
        ]);

        $this->artisan('grovemap:payment-reminders')->assertExitCode(0);
        $this->assertDatabaseCount('payment_reminder_deliveries', 1);
        $this->assertSame(1, $buyer->notifications()->count());
        $this->artisan('grovemap:payment-reminders')->assertExitCode(0);
        $this->assertSame(1, $buyer->notifications()->count());

        Payment::create([
            'transaction_id' => $transaction->id,
            'amount' => '500.00',
            'payment_date' => $today->toDateString(),
            'reference_number' => 'TEST-PAID-ALL',
            'method' => 'cash',
            'recorded_by_id' => $cashier->id,
            'verified_by_id' => $cashier->id,
            'verified_at' => now(),
        ]);
        // Even if the transaction's status has not yet changed, a fully funded schedule needs no reminder.
        $this->artisan('grovemap:payment-reminders')->assertExitCode(0);
        $this->assertSame(1, $buyer->notifications()->count());
        $this->assertSame($schedule->id, (int) \DB::table('payment_reminder_deliveries')->value('payment_schedule_id'));
    }

    public function test_overdue_reminder_sent_once_for_unpaid_installment(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $transaction = $this->sale($buyer, $agent);
        PaymentSchedule::create([
            'transaction_id' => $transaction->id,
            'installment_no' => 1,
            'due_date' => CarbonImmutable::today('Asia/Manila')->subDays(5)->toDateString(),
            'amount_due' => '500.00',
        ]);
        $this->artisan('grovemap:payment-reminders')->assertExitCode(0);
        $this->artisan('grovemap:payment-reminders')->assertExitCode(0);
        $this->assertSame(1, $buyer->notifications()->count());
        $this->assertDatabaseHas('payment_reminder_deliveries', ['kind' => 'overdue']);
    }
}
