<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $area = 'Toril'): User
    {
        return User::factory()->create(['role' => UserRole::Agent, 'service_area' => $area]);
    }

    public function test_buyer_contacts_the_agent_preassigned_to_the_lot(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $agent = $this->agent('Toril');
        $other = $this->agent('Kapalong');
        $lot = Lot::factory()->create(['assigned_agent_id' => $agent->id]);

        $this->actingAs($buyer)->get(route('inventory.lot', [$lot->property, $lot]))
            ->assertOk()->assertSee('Chat with Agent');
        $this->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Is it available?'])
            ->assertRedirect();

        $inquiry = Inquiry::sole();
        $this->assertSame($buyer->id, $inquiry->buyer_id);
        $this->assertSame($agent->id, $inquiry->agent_id);
        $this->assertDatabaseHas('messages', ['inquiry_id' => $inquiry->id, 'sender_id' => $buyer->id, 'body' => 'Is it available?']);
        $this->assertCount(1, $agent->fresh()->notifications);
        $this->actingAs($agent)->get(route('inquiries.index'))->assertOk()->assertSee('Lot '.$lot->lot_no);
        $this->get(route('inquiries.show', $inquiry))->assertOk()->assertSee('Is it available?');
        $this->actingAs($other)->get(route('inquiries.show', $inquiry))->assertForbidden();
    }

    public function test_repeated_contact_does_not_create_duplicate_inquiries_or_initial_messages(): void
    {
        $buyer = User::factory()->create();
        $lot = Lot::factory()->create();
        $this->actingAs($buyer);
        $route = route('inquiries.store', [$lot->property, $lot]);
        $this->post($route, ['message' => 'First'])->assertRedirect();
        $this->post($route, ['message' => 'Second'])->assertRedirect();
        $this->assertDatabaseCount('inquiries', 1);
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_unassigned_inquiry_is_routed_through_unit_manager_lot_assignment(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);
        $manager = User::factory()->create(['role' => 'unit_manager']);
        $lot = Lot::factory()->create();
        $this->actingAs($buyer)->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Please help'])->assertRedirect();
        $inquiry = Inquiry::sole();
        $this->assertNull($inquiry->agent_id);
        $this->actingAs($manager)->get(route('inquiries.index'))->assertOk()->assertSee('Awaiting assignment');
        $agent = User::factory()->create(['role' => 'agent', 'unit_manager_id' => $manager->id]);
        $this->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertRedirect();
        $this->assertSame($agent->id, $inquiry->fresh()->agent_id);
        $this->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->actingAs($agent)->get(route('inquiries.show', $inquiry))->assertOk()->assertSee('Please help');
    }

    public function test_only_participants_can_send_or_read_messages_and_poll_updates(): void
    {
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $agent = $this->agent();
        $lot = Lot::factory()->create();
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);

        $this->actingAs($otherBuyer)->get(route('inquiries.show', $inquiry))->assertForbidden();
        $this->post(route('inquiries.messages.store', $inquiry), ['body' => 'Intrusion'])->assertForbidden();
        $this->getJson(route('inquiries.updates', [$inquiry, 'after' => 0]))->assertForbidden();

        $this->actingAs($agent)->post(route('inquiries.messages.store', $inquiry), ['body' => '<script>alert(1)</script>'])->assertRedirect();
        $message = Message::sole();
        $this->actingAs($buyer)->get(route('inquiries.show', $inquiry))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNotNull($message->fresh()->read_at);
        $this->getJson(route('inquiries.updates', [$inquiry, 'after' => 0]))->assertOk()
            ->assertJsonPath('messages.0.body', '<script>alert(1)</script>')
            ->assertJsonPath('messages.0.mine', false);
    }

    public function test_unverified_users_and_wrong_roles_cannot_create_inquiries(): void
    {
        $lot = Lot::factory()->create();
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Hello'])->assertRedirect();
        $this->actingAs($this->agent())
            ->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Hello'])->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->postJson(route('inquiries.store', [$lot->property, $lot]), ['message' => ''])->assertUnprocessable();
    }

    public function test_sold_lots_cannot_start_new_inquiries(): void
    {
        $buyer = User::factory()->create();
        $lot = Lot::factory()->create(['status' => 'sold']);
        $this->actingAs($buyer)->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Hello'])->assertStatus(409);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_manager_lot_assignment_notifies_agent_about_pending_inquiry(): void
    {
        $manager = User::factory()->create(['role' => 'unit_manager']);
        $agent = User::factory()->create(['role' => 'agent', 'unit_manager_id' => $manager->id]);
        $buyer = User::factory()->create();
        $lot = Lot::factory()->create();
        $this->actingAs($buyer)->post(route('inquiries.store', [$lot->property, $lot]), ['message' => 'Hello'])->assertRedirect();
        $this->actingAs($manager)->patch(route('assignments.update', $lot), ['agent_id' => $agent->id])->assertRedirect();
        $this->assertCount(1, $agent->fresh()->unreadNotifications);
        $this->actingAs($agent)->post(route('inquiries.notifications.read'))->assertRedirect();
        $this->assertCount(0, $agent->fresh()->unreadNotifications);
    }
}
