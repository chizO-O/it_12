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
use App\Support\ReservationProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibleForSaleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupPending(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $planner = User::factory()->create(['role' => UserRole::FieldPlanner]);
        $secretary = User::factory()->create(['role' => UserRole::Admin]);
        $lot = Lot::factory()->create(['assigned_agent_id' => $agent->id, 'assigned_by_user_id' => $manager->id]);
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);

        $this->actingAs($agent)->post(route('reservations.store', $inquiry), [
            'confirmed_interest' => '1',
        ])->assertRedirect();
        $reservation = Reservation::latest('id')->firstOrFail();
        $this->actingAs($buyer)->put(route('reservations.details', $reservation), [
            'contact_number' => '09123456789', 'address' => 'Sample buyer address',
        ])->assertRedirect();

        return compact('buyer', 'manager', 'agent', 'planner', 'secretary', 'lot', 'reservation');
    }

    public function test_eligible_for_sale_creates_cts_and_opens_parallel_buyer_and_seller_forms(): void
    {
        extract($this->setupPending());
        $this->assertDatabaseCount('cts_records', 0);
        $this->actingAs($manager)->patch(route('reservations.review', $reservation), ['decision' => 'approve'])
            ->assertRedirect();
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertDatabaseCount('cts_records', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
        $transaction = $reservation->fresh()->transaction;
        $this->assertSame('cts_processing', $transaction->status);
        $cts = $transaction->ctsRecord;
        $this->assertSame(CtsStatus::Requested, $cts->status);
        $this->assertSame($manager->id, $cts->initiated_by_id);

        $this->actingAs($buyer)->get(route('reservations.index'))->assertOk()->assertSee('CTS processing');
        $this->get(route('cts.for-reservation', $reservation))->assertRedirect(route('cts.show', $cts));
        $this->get(route('cts.show', $cts))->assertOk()
            ->assertSee('Buyer legal full name')->assertSee('Marital status')->assertSee('Nationality');
        $this->actingAs($planner)->get(route('cts.index'))->assertOk()->assertSee('Lot '.$lot->lot_no);
        $this->get(route('cts.show', $cts))->assertOk()->assertSee('Seller / Landowner full name');
        $stranger = User::factory()->create(['role' => UserRole::Buyer]);
        $this->actingAs($stranger)->get(route('cts.for-reservation', $reservation))->assertForbidden();
    }

    public function test_secretary_waits_for_both_party_submissions_before_drafting(): void
    {
        extract($this->setupPending());
        $this->actingAs($manager)->patch(route('reservations.review', $reservation), ['decision' => 'approve'])
            ->assertRedirect();
        $cts = $reservation->fresh()->transaction->ctsRecord;
        $this->actingAs($secretary)->get(route('cts.show', $cts))->assertOk()
            ->assertSee('Waiting for complete buyer and seller details');
        $this->assertFalse($secretary->can('prepare', $cts));

        $this->actingAs($buyer)->patch(route('cts.buyer-details', $cts), [
            'buyer_full_name' => 'Sample Buyer Legal Name',
            'buyer_permanent_address' => 'Sample City',
            'buyer_marital_status' => 'Single',
            'buyer_age' => 30,
            'buyer_nationality' => 'Filipino',
        ])->assertRedirect();
        $this->assertFalse($cts->fresh()->partiesReady());
        $this->assertFalse($secretary->can('prepare', $cts->fresh()));

        $this->actingAs($planner)->patch(route('cts.verify', $cts), [
            'verified' => 1, 'landowner_name' => 'Sample Seller Legal Name',
            'landowner_contact' => '09123456789', 'landowner_address' => 'Seller current address',
            'landowner_permanent_address' => 'Seller permanent address',
            'landowner_marital_status' => 'Married', 'landowner_age' => 55,
            'landowner_nationality' => 'Filipino',
        ])->assertRedirect();
        $this->assertTrue($cts->fresh()->partiesReady());
        $this->assertTrue($secretary->can('prepare', $cts->fresh()));
        $this->actingAs($secretary)->get(route('cts.show', $cts))->assertOk()
            ->assertSee('Sample Buyer Legal Name')->assertSee('Sample Seller Legal Name')
            ->assertSee('Secretary/Admin — prepare contract information');
    }

    public function test_reservation_progress_is_derived_from_sale_milestones_not_cts_document_state(): void
    {
        extract($this->setupPending());
        $this->actingAs($manager)->patch(route('reservations.review', $reservation), ['decision' => 'approve'])
            ->assertRedirect();
        $reservation = $reservation->fresh()->load(['lot', 'transaction.ctsRecord']);
        $this->assertSame('CTS processing', ReservationProgress::label($reservation));

        // A document-only status update must not change the reservation progress.
        $reservation->transaction->ctsRecord->status = CtsStatus::Completed;
        $reservation->transaction->ctsRecord->save();
        $this->assertSame('CTS processing', ReservationProgress::label($reservation));

        $reservation->transaction->status = 'cts_completed';
        $reservation->transaction->save();
        $this->actingAs($buyer)->get(route('reservations.index'))->assertOk()
            ->assertSee('CTS completed — sale pending');

        $reservation->transaction->status = 'sold';
        $reservation->transaction->save();
        $reservation->lot->status = LotStatus::Sold;
        $reservation->lot->save();
        $this->actingAs($buyer)->get(route('reservations.index'))->assertOk()
            ->assertSee('Sold — payment ongoing');

        $reservation->transaction->status = 'fully_paid';
        $reservation->transaction->save();
        $reservation->lot->status = LotStatus::FullyPaid;
        $reservation->lot->save();
        $this->actingAs($buyer)->get(route('reservations.index'))->assertOk()
            ->assertSee('Fully paid');
    }
}
