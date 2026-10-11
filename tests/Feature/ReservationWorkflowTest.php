<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $lot = Lot::factory()->create(['assigned_agent_id' => $agent->id, 'assigned_by_user_id' => $manager->id]);
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);

        return compact('buyer', 'agent', 'manager', 'lot', 'inquiry');
    }

    private function reserve(User $agent, Inquiry $inquiry): Reservation
    {
        $this->actingAs($agent)->post(route('reservations.store', $inquiry), [
            'confirmed_interest' => '1',
            'agent_notes' => 'Buyer confirmed interest during our conversation.',
        ])->assertRedirect();

        return Reservation::query()->latest('id')->firstOrFail();
    }

    public function test_agent_reserves_lot_and_notifies_buyer_and_manager(): void
    {
        extract($this->scenario());
        $reservation = $this->reserve($agent, $inquiry);

        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertSame($buyer->id, $reservation->buyer_id);
        $this->assertSame($agent->id, $reservation->agent_id);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
        $this->assertCount(1, $manager->fresh()->notifications);
        $this->assertCount(1, $buyer->fresh()->notifications);
        $this->actingAs($buyer)->get(route('reservations.index'))->assertOk()->assertSee('Pending review');
        $this->actingAs($manager)->get(route('reservations.show', $reservation))->assertOk()->assertSee($buyer->name);
    }

    public function test_only_assigned_verified_agent_can_reserve_after_interest_confirmed(): void
    {
        extract($this->scenario());
        $other = User::factory()->create(['role' => UserRole::Agent]);
        $buyerPayload = ['confirmed_interest' => '1'];
        $this->actingAs($buyer)->post(route('reservations.store', $inquiry), $buyerPayload)->assertForbidden();
        $this->actingAs($other)->post(route('reservations.store', $inquiry), $buyerPayload)->assertForbidden();
        $this->actingAs($agent)->post(route('reservations.store', $inquiry), [])->assertSessionHasErrors('confirmed_interest');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_second_reservation_cannot_take_a_reserved_lot(): void
    {
        extract($this->scenario());
        $anotherBuyer = User::factory()->create();
        $secondInquiry = Inquiry::create(['buyer_id' => $anotherBuyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);
        $this->reserve($agent, $inquiry);
        $this->actingAs($agent)->post(route('reservations.store', $secondInquiry), ['confirmed_interest' => 1])
            ->assertSessionHasErrors('reservation');
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
    }

    public function test_database_rejects_a_second_active_reservation_for_the_same_lot(): void
    {
        extract($this->scenario());
        $first = $this->reserve($agent, $inquiry);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Reservation::query()->insert([
            'buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id,
            'inquiry_id' => $inquiry->id, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_buyer_can_submit_details_then_unit_manager_can_approve(): void
    {
        extract($this->scenario());
        $reservation = $this->reserve($agent, $inquiry);
        $this->actingAs($manager)->patch(route('reservations.review', $reservation), ['decision' => 'approve'])
            ->assertSessionHasErrors('reservation');
        $this->actingAs($buyer)->put(route('reservations.details', $reservation), [
            'contact_number' => '09123456789', 'address' => 'Toril, Davao City', 'notes' => 'Interested in full cash.',
        ])->assertRedirect();
        $this->assertDatabaseHas('buyer_requirements', ['reservation_id' => $reservation->id, 'contact_number' => '09123456789']);
        $this->actingAs($manager)->patch(route('reservations.review', $reservation), ['decision' => 'approve'])
            ->assertRedirect();
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
        $this->actingAs($buyer)->put(route('reservations.details', $reservation), [
            'contact_number' => '09999999999', 'address' => 'Changed',
        ])->assertSessionHasErrors('reservation');
    }

    public function test_rejection_reopens_the_lot_and_keeps_history(): void
    {
        extract($this->scenario());
        $reservation = $this->reserve($agent, $inquiry);
        $this->actingAs($manager)->patch(route('reservations.review', $reservation), [
            'decision' => 'reject', 'review_notes' => 'Documents incomplete.',
        ])->assertRedirect();
        $this->assertSame(ReservationStatus::Rejected, $reservation->fresh()->status);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'available']);
        $this->assertDatabaseCount('reservations', 1);
        // A later attempt using the same inquiry is permitted after rejection.
        $again = $this->reserve($agent, $inquiry);
        $this->assertNotSame($reservation->id, $again->id);
    }

    public function test_agent_can_cancel_pending_but_not_approved_reservations(): void
    {
        extract($this->scenario());
        $reservation = $this->reserve($agent, $inquiry);
        $this->actingAs($agent)->patch(route('reservations.cancel', $reservation), ['reason' => 'Buyer withdrew.'])
            ->assertRedirect();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'available']);

        $next = $this->reserve($agent, $inquiry);
        $this->actingAs($buyer)->put(route('reservations.details', $next), [
            'contact_number' => '09123456789', 'address' => 'Toril',
        ])->assertRedirect();
        $this->actingAs($manager)->patch(route('reservations.review', $next), ['decision' => 'approve'])
            ->assertRedirect();
        $this->actingAs($agent)->patch(route('reservations.cancel', $next), ['reason' => 'Agent attempting cancellation'])
            ->assertForbidden();
        $this->assertSame(ReservationStatus::Approved, $next->fresh()->status);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
    }

    public function test_buyer_and_agent_cannot_read_others_reservation_or_edit_details(): void
    {
        extract($this->scenario());
        $reservation = $this->reserve($agent, $inquiry);
        $otherBuyer = User::factory()->create();
        $otherAgent = User::factory()->create(['role' => UserRole::Agent]);
        $payload = ['contact_number' => '09123456789', 'address' => 'Unknown'];
        $this->actingAs($otherBuyer)->get(route('reservations.show', $reservation))->assertForbidden();
        $this->put(route('reservations.details', $reservation), $payload)->assertForbidden();
        $this->actingAs($otherAgent)->get(route('reservations.show', $reservation))->assertForbidden();
        $this->actingAs($agent)->put(route('reservations.details', $reservation), $payload)->assertForbidden();
        $this->actingAs($buyer)->patch(route('reservations.review', $reservation), ['decision' => 'approve'])
            ->assertForbidden();
        $this->assertDatabaseCount('buyer_requirements', 0);
    }

    public function test_cancelled_lot_with_inquiry_history_cannot_be_deleted(): void
    {
        extract($this->scenario());
        $reservation = $this->reserve($agent, $inquiry);
        $this->actingAs($agent)->patch(route('reservations.cancel', $reservation), ['reason' => 'Buyer withdrew.'])
            ->assertRedirect();
        $planner = User::factory()->create(['role' => UserRole::FieldPlanner]);
        $this->actingAs($planner)->delete(route('properties.lots.destroy', [$lot->property, $lot]))
            ->assertSessionHasErrors('lot');
        $this->assertDatabaseHas('lots', ['id' => $lot->id]);
    }
}
