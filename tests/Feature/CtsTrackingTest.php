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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CtsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function setupApproved(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $planner = User::factory()->create(['role' => UserRole::FieldPlanner]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $lot = Lot::factory()->create([
            'assigned_agent_id' => $agent->id,
            'assigned_by_user_id' => $manager->id,
            'status' => LotStatus::Reserved,
        ]);
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);
        $reservation = new Reservation;
        $reservation->lot_id = $lot->id;
        $reservation->inquiry_id = $inquiry->id;
        $reservation->buyer_id = $buyer->id;
        $reservation->agent_id = $agent->id;
        $reservation->status = ReservationStatus::Approved;
        $reservation->reviewed_by = $manager->id;
        $reservation->reviewed_at = now();
        $reservation->save();

        return compact('buyer', 'manager', 'agent', 'planner', 'admin', 'lot', 'reservation');
    }

    private function initiated(array $data): CtsRecord
    {
        $this->actingAs($data['manager'])->post(route('cts.initiate', $data['reservation']))->assertRedirect();

        return CtsRecord::query()->firstOrFail();
    }

    private function storageConfigured(): void
    {
        config()->set('services.supabase', [
            'url' => 'https://sample.supabase.co',
            'service_role_key' => 'TEST_FAKE_SERVER_ONLY',
            'cts_bucket' => 'grovemap-cts',
        ]);
        Http::fake([
            'https://sample.supabase.co/storage/v1/object/sign/*' => Http::response([
                'signedURL' => '/object/sign/grovemap-cts/cts/1/draft/test.pdf?token=fake',
            ]),
            'https://sample.supabase.co/storage/v1/object/*' => Http::response(['Key' => 'test'], 200),
        ]);
    }

    public function test_only_responsible_manager_can_initiate_one_cts_from_approved_reservation(): void
    {
        $data = $this->setupApproved();
        $other = User::factory()->create(['role' => UserRole::UnitManager]);
        $this->actingAs($other)->post(route('cts.initiate', $data['reservation']))->assertForbidden();
        $this->actingAs($data['buyer'])->post(route('cts.initiate', $data['reservation']))->assertForbidden();
        $cts = $this->initiated($data);
        $this->assertSame(CtsStatus::Requested, $cts->status);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('cts_records', 1);
        $this->assertDatabaseHas('lots', ['id' => $data['lot']->id, 'status' => 'reserved']);
        $this->actingAs($data['manager'])->post(route('cts.initiate', $data['reservation']))->assertSessionHasErrors('cts');
        $this->assertDatabaseCount('cts_records', 1);
    }

    public function test_cts_statuses_follow_role_authorized_sequence_without_changing_lot_status(): void
    {
        $data = $this->setupApproved();
        $cts = $this->initiated($data);
        $this->storageConfigured();

        $this->actingAs($data['admin'])->post(route('cts.draft', $cts), [
            'document' => UploadedFile::fake()->create('draft.pdf', 25, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($data['buyer'])->patch(route('cts.review', $cts), ['decision' => 'acknowledge'])->assertForbidden();

        $this->actingAs($data['planner'])->patch(route('cts.verify', $cts), [
            'verified' => 1, 'verification_notes' => 'Survey details and landowner documents checked off-platform.',
        ])->assertRedirect();
        $this->assertSame(CtsStatus::PropertyVerified, $cts->fresh()->status);

        $this->actingAs($data['admin'])->post(route('cts.draft', $cts), [
            'document' => UploadedFile::fake()->create('draft.pdf', 25, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(CtsStatus::ForBuyerReview, $cts->fresh()->status);
        $this->actingAs($data['buyer'])->patch(route('cts.review', $cts), [
            'decision' => 'request_revision', 'feedback' => 'Please correct the spelling of my address.',
        ])->assertRedirect();
        $this->assertSame(CtsStatus::RevisionRequested, $cts->fresh()->status);

        $this->actingAs($data['admin'])->post(route('cts.draft', $cts), [
            'document' => UploadedFile::fake()->create('revision.pdf', 25, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(2, $cts->fresh()->draft_version);

        $this->actingAs($data['buyer'])->patch(route('cts.review', $cts), ['decision' => 'acknowledge'])->assertRedirect();
        $this->assertSame(CtsStatus::ForSigning, $cts->fresh()->status);
        $this->actingAs($data['admin'])->post(route('cts.complete', $cts), [
            'document' => UploadedFile::fake()->create('executed.pdf', 25, 'application/pdf'),
            'notarized_at' => now()->toDateString(),
            'external_process_confirmed' => 1,
        ])->assertRedirect();
        $this->assertSame(CtsStatus::Completed, $cts->fresh()->status);
        $this->assertSame('cts_completed', Transaction::first()->status);
        $this->assertDatabaseHas('lots', ['id' => $data['lot']->id, 'status' => 'reserved']);
    }

    public function test_cts_documents_and_reviews_are_private(): void
    {
        $data = $this->setupApproved();
        $cts = $this->initiated($data);
        $otherBuyer = User::factory()->create(['role' => UserRole::Buyer]);
        $otherManager = User::factory()->create(['role' => UserRole::UnitManager]);
        $cashier = User::factory()->create(['role' => UserRole::Cashier]);
        $this->actingAs($otherBuyer)->get(route('cts.show', $cts))->assertForbidden();
        $this->actingAs($otherBuyer)->get(route('cts.document', [$cts, 'draft']))->assertForbidden();
        $this->actingAs($otherManager)->get(route('cts.show', $cts))->assertForbidden();
        $this->actingAs($cashier)->get(route('cts.index'))->assertForbidden();
        $this->actingAs($data['buyer'])->get(route('cts.show', $cts))->assertOk();
        $this->actingAs($data['manager'])->get(route('cts.show', $cts))->assertOk();
        $this->actingAs($data['planner'])->get(route('cts.show', $cts))->assertOk();
        $this->actingAs($data['planner'])->get(route('cts.document', [$cts, 'draft']))->assertForbidden();
    }

    public function test_approved_reservation_with_cts_cannot_be_cancelled_using_normal_reservation_action(): void
    {
        $data = $this->setupApproved();
        $this->initiated($data);
        $this->actingAs($data['manager'])->patch(route('reservations.cancel', $data['reservation']), [
            'reason' => 'Trying to release the lot with an active CTS process.',
        ])->assertSessionHasErrors('reservation');
        $this->assertSame(ReservationStatus::Approved, $data['reservation']->fresh()->status);
    }
}
