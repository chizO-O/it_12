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
        $this->actingAs($data['admin'])->post(route('cts.initiate', $data['reservation']))->assertRedirect();

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

    public function test_only_secretary_can_open_one_cts_for_approved_reservation(): void
    {
        $data = $this->setupApproved();
        $other = User::factory()->create(['role' => UserRole::UnitManager]);
        $this->actingAs($other)->post(route('cts.initiate', $data['reservation']))->assertForbidden();
        $this->actingAs($data['manager'])->post(route('cts.initiate', $data['reservation']))->assertForbidden();
        $this->actingAs($data['buyer'])->post(route('cts.initiate', $data['reservation']))->assertForbidden();
        $cts = $this->initiated($data);
        $this->assertSame(CtsStatus::Requested, $cts->status);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('cts_records', 1);
        $this->assertDatabaseHas('lots', ['id' => $data['lot']->id, 'status' => 'reserved']);
        $this->actingAs($data['admin'])->post(route('cts.initiate', $data['reservation']))->assertSessionHasErrors('cts');
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
            'landowner_name' => 'Seller Sample', 'landowner_contact' => '09123456789',
            'landowner_address' => 'Davao City', 'landowner_permanent_address' => 'Davao City',
            'landowner_marital_status' => 'Single', 'landowner_age' => 49, 'landowner_nationality' => 'Filipino',
            'landowner_relationship' => 'Registered owner',
        ])->assertRedirect();
        $this->assertSame(CtsStatus::PropertyVerified, $cts->fresh()->status);

        $this->actingAs($data['buyer'])->patch(route('cts.buyer-details', $cts), [
            'buyer_full_name' => 'Sample Buyer CTS Name',
            'buyer_permanent_address' => 'Davao City', 'buyer_marital_status' => 'Single',
            'buyer_age' => 27, 'buyer_nationality' => 'Filipino',
        ])->assertRedirect();

        $this->actingAs($data['admin'])->patch(route('cts.prepare', $cts), [
            'contract_price' => '500000.00',
            'ownership_agreement' => 'Registered owner agrees to transfer title following the documented conditions.',
            'payment_agreement' => 'Buyer and seller agreed to the purchase terms written in the externally prepared CTS.',
            'terms_verified' => 1,
        ])->assertRedirect();
        $this->assertSame('500000.00', $cts->fresh()->contract_price);
        $this->actingAs($data['buyer'])->get(route('cts.show', $cts))->assertOk()
            ->assertSee('Registered owner agrees to transfer title');

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

    public function test_secretary_sees_approved_reservations_and_buyer_sees_own_contract_lot(): void
    {
        $data = $this->setupApproved();
        $this->actingAs($data['admin'])->get(route('cts.index'))->assertOk()
            ->assertSee('Open legacy CTS record')->assertSee($data['buyer']->name);
        $cts = $this->initiated($data);
        $this->actingAs($data['admin'])->get(route('cts.index'))->assertOk()
            ->assertDontSee('Open legacy CTS record</button>', false);
        $this->actingAs($data['buyer'])->get(route('cts.index'))->assertOk()
            ->assertSee('My CTS documents')->assertSee('Lot '.$data['lot']->lot_no);
        $cts->draft_path = 'cts/sample-draft.pdf';
        $cts->save();
        $this->actingAs($data['buyer'])->get(route('cts.index'))->assertOk()
            ->assertSee('Lot '.$data['lot']->lot_no);
        $stranger = User::factory()->create(['role' => UserRole::Buyer]);
        $this->actingAs($stranger)->get(route('cts.index'))->assertOk()
            ->assertDontSee($data['buyer']->name);
        $this->actingAs($stranger)->get(route('cts.show', $cts))->assertForbidden();
    }

    public function test_secretary_cannot_upload_new_draft_until_agreed_cts_details_are_saved(): void
    {
        $data = $this->setupApproved();
        $cts = $this->initiated($data);
        $this->actingAs($data['planner'])->patch(route('cts.verify', $cts), [
            'verified' => 1, 'landowner_name' => 'Registered Seller',
            'landowner_contact' => '09123456789', 'landowner_address' => 'Davao City',
            'landowner_permanent_address' => 'Davao City', 'landowner_marital_status' => 'Single',
            'landowner_age' => 48, 'landowner_nationality' => 'Filipino',
        ])->assertRedirect();
        $this->actingAs($data['admin'])->post(route('cts.draft', $cts), [
            'document' => UploadedFile::fake()->create('draft.pdf', 25, 'application/pdf'),
        ])->assertSessionHasErrors('cts');
        $this->assertNull($cts->fresh()->draft_path);
        $this->actingAs($data['agent'])->patch(route('cts.prepare', $cts), [
            'contract_price' => 100, 'ownership_agreement' => str_repeat('x', 20),
            'payment_agreement' => str_repeat('x', 20), 'terms_verified' => 1,
        ])->assertForbidden();
    }

    public function test_field_planner_landowner_form_becomes_read_only_and_can_be_edited_until_cts_is_completed(): void
    {
        $data = $this->setupApproved();
        $cts = $this->initiated($data);
        $this->actingAs($data['planner'])->get(route('cts.show', $cts))
            ->assertOk()->assertSee('Submit Landowner Information')->assertSee('landowner_name');
        $this->patch(route('cts.verify', $cts), [
            'verified' => 1, 'landowner_name' => 'First Seller', 'landowner_contact' => '09123456789',
            'landowner_address' => 'Original Davao Address', 'landowner_relationship' => 'Registered owner',
            'landowner_permanent_address' => 'Davao City', 'landowner_marital_status' => 'Single',
            'landowner_age' => 47, 'landowner_nationality' => 'Filipino',
            'verification_notes' => 'Verified external property data.',
        ])->assertRedirect();
        $this->get(route('cts.show', $cts))->assertOk()->assertSee('First Seller')
            ->assertSee('Edit Information')->assertDontSee('name="landowner_name"', false);
        $this->get(route('cts.show', [$cts, 'edit_landowner' => 1]))
            ->assertOk()->assertSee('Save Changes')->assertSee('Cancel')->assertSee('name="landowner_name"', false);
        $this->patch(route('cts.verify', $cts), [
            'verified' => 1, 'landowner_name' => 'Corrected Seller', 'landowner_contact' => '09123456789',
            'landowner_address' => 'Corrected Address', 'verification_notes' => 'Corrected off-platform details.',
            'landowner_permanent_address' => 'Toril, Davao City', 'landowner_marital_status' => 'Married',
            'landowner_age' => 48, 'landowner_nationality' => 'Filipino',
        ])->assertRedirect();
        $this->assertSame('Corrected Seller', $cts->fresh()->landowner_name);
        $this->assertSame(CtsStatus::PropertyVerified, $cts->fresh()->status);

        $cts->status = CtsStatus::Completed;
        $cts->completed_at = now();
        $cts->save();
        $this->get(route('cts.show', [$cts, 'edit_landowner' => 1]))
            ->assertOk()->assertSee('read-only')->assertDontSee('name="landowner_name"', false);
        $this->patch(route('cts.verify', $cts), [
            'verified' => 1, 'landowner_name' => 'Forged Seller', 'landowner_contact' => '09123456789',
            'landowner_address' => 'Forged Address',
        ])->assertForbidden();
        $this->assertSame('Corrected Seller', $cts->fresh()->landowner_name);
    }

    public function test_buyer_cts_personal_details_are_private_editable_and_locked_after_completion(): void
    {
        $data = $this->setupApproved();
        $cts = $this->initiated($data);
        $stranger = User::factory()->create(['role' => UserRole::Buyer]);
        $payload = [
            'buyer_full_name' => 'CTS Buyer Sample',
            'buyer_permanent_address' => 'Toril, Davao City',
            'buyer_marital_status' => 'Single',
            'buyer_age' => 29,
            'buyer_nationality' => 'Filipino',
        ];
        $this->actingAs($stranger)->patch(route('cts.buyer-details', $cts), $payload)->assertForbidden();
        $this->actingAs($data['buyer'])->get(route('cts.index'))->assertOk()
            ->assertSee('Lot '.$data['lot']->lot_no);
        $this->patch(route('cts.buyer-details', $cts), $payload)->assertRedirect();
        $this->assertSame('Toril, Davao City', $cts->fresh()->buyer_permanent_address);
        $this->get(route('cts.show', $cts))->assertOk()
            ->assertSee('Edit Information')->assertDontSee('name="buyer_age"', false);
        $this->get(route('cts.show', [$cts, 'edit_buyer_details' => 1]))->assertOk()
            ->assertSee('Save Changes')->assertSee('name="buyer_age"', false);
        $this->patch(route('cts.buyer-details', $cts), array_replace($payload, ['buyer_age' => 30]))->assertRedirect();
        $this->assertSame(30, $cts->fresh()->buyer_age);

        $cts->status = CtsStatus::Completed;
        $cts->save();
        $this->patch(route('cts.buyer-details', $cts), $payload)->assertForbidden();
        $this->assertSame(30, $cts->fresh()->buyer_age);
        $this->get(route('cts.show', $cts))->assertOk()->assertDontSee('name="buyer_age"', false);
    }

}
