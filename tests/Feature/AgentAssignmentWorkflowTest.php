<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AgentAssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function people(): array
    {
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $lot = Lot::factory()->create();
        return compact('manager', 'agent', 'buyer', 'lot');
    }

    public function test_manager_can_invite_agent_but_admin_cannot(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $payload = ['name' => 'Agent Test', 'email' => 'new-agent@example.com'];
        $this->actingAs($admin)->post(route('employees.store'), $payload + ['role' => 'agent'])
            ->assertSessionHasErrors('role');
        $this->actingAs($manager)->post(route('agents.store'), $payload)
            ->assertRedirect(route('agents.index'))->assertSessionHas('activation_code');
        $agent = User::where('email', 'new-agent@example.com')->firstOrFail();
        $this->assertSame(UserRole::Agent, $agent->role);
        $this->assertSame($manager->id, $agent->unit_manager_id);
        Notification::assertNotSentTo($agent, ResetPassword::class);
        $this->assertNull($agent->password);
    }

    public function test_manager_can_link_an_existing_unmanaged_agent(): void
    {
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $oldAgent = User::factory()->create(['role' => UserRole::Agent]);
        $this->actingAs($manager)->post(route('agents.claim', $oldAgent))->assertRedirect();
        $this->assertSame($manager->id, $oldAgent->fresh()->unit_manager_id);
    }

    public function test_only_manager_supervising_agent_can_assign_available_lot(): void
    {
        extract($this->people());
        $otherManager = User::factory()->create(['role' => UserRole::UnitManager]);
        $this->actingAs($otherManager)->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertForbidden();
        $this->actingAs($manager)->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertRedirect();
        $this->assertSame($agent->id, $lot->fresh()->assigned_agent_id);
        $this->assertSame('available', $lot->fresh()->getRawOriginal('status'));
    }

    public function test_buyer_inquiry_routes_to_lot_agent_even_if_other_agents_exist(): void
    {
        extract($this->people());
        User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id, 'service_area' => 'Toril']);
        $this->actingAs($manager)->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertRedirect();
        $this->actingAs($buyer)->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Hello Lot'])->assertRedirect();
        $this->assertSame($agent->id, Inquiry::sole()->agent_id);
    }

    public function test_unassigned_inquiry_is_routed_when_manager_assigns_lot(): void
    {
        extract($this->people());
        $this->actingAs($buyer)->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Please help'])->assertRedirect();
        $this->assertNull(Inquiry::sole()->agent_id);
        $this->actingAs($manager)->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertRedirect();
        $this->assertSame($agent->id, Inquiry::sole()->agent_id);
    }

    public function test_reassigning_a_lot_does_not_transfer_private_existing_messages(): void
    {
        extract($this->people());
        $other = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $this->actingAs($manager)->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertRedirect();
        Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);
        $this->patch(route('assignments.update', $lot), ['agent_id' => $other->id])->assertSessionHasErrors('agent_id');
        $this->assertSame($agent->id, $lot->fresh()->assigned_agent_id);
    }

    public function test_only_lot_assigned_agent_can_reserve(): void
    {
        extract($this->people());
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);
        $this->actingAs($agent)->post(route('reservations.store', $inquiry), ['confirmed_interest' => '1'])->assertForbidden();
        $this->actingAs($manager)->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertRedirect();
        $this->actingAs($agent)->post(route('reservations.store', $inquiry), ['confirmed_interest' => '1'])->assertRedirect();
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
    }
}
