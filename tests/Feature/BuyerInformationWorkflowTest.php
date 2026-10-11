<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\BuyerAssessment;
use App\Models\BuyerInformationCorrection;
use App\Models\Inquiry;
use App\Models\Lot;
use App\Models\Reservation;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuyerInformationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $buyer = User::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::UnitManager]);
        $agent = User::factory()->create(['role' => UserRole::Agent, 'unit_manager_id' => $manager->id]);
        $lot = Lot::factory()->create(['assigned_agent_id' => $agent->id, 'assigned_by_user_id' => $manager->id]);
        $inquiry = Inquiry::create(['buyer_id' => $buyer->id, 'agent_id' => $agent->id, 'lot_id' => $lot->id]);
        $this->actingAs($agent)->post(route('reservations.store', $inquiry), ['confirmed_interest' => 1])->assertRedirect();
        $reservation = Reservation::latest('id')->firstOrFail();

        return compact('buyer', 'manager', 'agent', 'lot', 'inquiry', 'reservation');
    }

    private function contact(User $buyer, Reservation $reservation): void
    {
        $this->actingAs($buyer)->put(route('reservations.details', $reservation), [
            'contact_number' => '09123456789', 'address' => 'Buyer-provided address', 'notes' => 'Alternate number: 09876543210',
        ])->assertRedirect(route('reservations.show', $reservation));
    }

    private function assessment(User $agent, Reservation $reservation, array $changes = []): BuyerAssessment
    {
        $this->actingAs($agent)->put(route('reservations.assessment', $reservation), array_replace([
            'employment_status' => 'Self-employed', 'source_of_income' => 'Retail business',
            'monthly_income_range' => 'PHP 50,000–80,000', 'preferred_payment_method' => 'installment',
            'requested_installment_months' => '36', 'proposed_down_payment' => '150000.25',
            'additional_clarifications' => 'Buyer requests schedule clarification.',
            'agent_remarks' => 'Internal remark: follow up privately.', 'privacy_notice_confirmed' => '1',
        ], $changes))->assertRedirect(route('reservations.show', $reservation));

        return $reservation->buyerAssessment()->firstOrFail();
    }

    private function approve(User $manager, Reservation $reservation): void
    {
        $this->actingAs($manager)->patch(route('reservations.review', $reservation), ['decision' => 'approve'])->assertRedirect();
    }

    private function correction(User $user, Reservation $reservation, array $changes = []): BuyerInformationCorrection
    {
        $this->actingAs($user)->post(route('reservations.corrections.store', $reservation), array_replace([
            'section' => 'assessment', 'field' => 'employment_status', 'proposed_value' => 'Employed',
            'reason' => 'The employment information was recorded incorrectly.',
        ], $changes))->assertRedirect(route('reservations.show', $reservation));

        return $reservation->informationCorrections()->latest('id')->firstOrFail();
    }

    public function test_contact_submission_hides_form_and_edit_mode_updates_the_same_record(): void
    {
        extract($this->scenario());
        $url = route('reservations.show', $reservation);
        $this->actingAs($buyer)->get($url)->assertOk()->assertSee("Buyer's Contact Information", false)->assertSee('name="contact_number"', false)->assertSee('Submit Contact Information');
        $this->contact($buyer, $reservation);
        $record = $reservation->buyerForm()->firstOrFail();
        $this->get($url)->assertOk()->assertSee('09123456789')->assertSee('Edit Information')->assertDontSee('name="contact_number"', false);
        $this->get(route('reservations.show', [$reservation, 'edit_contact' => 1]))->assertOk()->assertSee('value="09123456789"', false)->assertSee('Buyer-provided address')->assertSee('Save Changes')->assertSee('Cancel');
        // Returning from edit mode performs no write and hides the form again.
        $this->get($url)->assertOk()->assertDontSee('Save Changes');
        $this->assertSame('Buyer-provided address', $record->fresh()->address);
        $this->put(route('reservations.details', $reservation), ['contact_number' => '09999999999', 'address' => 'Updated address'])->assertRedirect($url);
        $this->assertSame($record->id, $reservation->buyerForm()->firstOrFail()->id);
        $this->assertSame('Updated address', $record->fresh()->address);
        $this->assertSame($buyer->id, $record->fresh()->submitted_by);
        $this->assertDatabaseCount('buyer_requirements', 1);
        $this->get($url)->assertSee('Updated address')->assertDontSee('Save Changes');
    }

    public function test_contact_is_read_only_after_approval_even_when_edit_mode_is_requested(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $this->approve($manager, $reservation);
        $this->actingAs($buyer)->get(route('reservations.show', [$reservation, 'edit_contact' => 1]))->assertOk()->assertSee('Contact information is read-only')->assertDontSee('Edit Information')->assertDontSee('name="contact_number"', false);
        $this->put(route('reservations.details', $reservation), ['contact_number' => '09999999999', 'address' => 'Forged'])->assertSessionHasErrors('reservation');
        $this->assertSame('Buyer-provided address', $reservation->buyerForm()->first()->address);
    }

    public function test_assessment_create_and_update_preserve_author_identity_and_notice_date(): void
    {
        extract($this->scenario());
        $record = $this->assessment($agent, $reservation, ['recorded_by' => $buyer->id, 'last_updated_by' => $buyer->id, 'reservation_id' => 999, 'privacy_notice_given_at' => '2000-01-01']);
        $this->assertSame($agent->id, $record->recorded_by);
        $this->assertSame($agent->id, $record->last_updated_by);
        $this->assertSame('150000.25', $record->proposed_down_payment);
        $this->assertSame(36, $record->requested_installment_months);
        $notice = $record->privacy_notice_given_at->toISOString();
        $this->travel(5)->minutes();
        $next = $this->assessment($agent, $reservation, ['employment_status' => 'Employed', 'recorded_by' => $manager->id]);
        $this->assertSame($record->id, $next->id);
        $this->assertSame($agent->id, $next->recorded_by);
        $this->assertSame($notice, $next->privacy_notice_given_at->toISOString());
        $this->assertTrue($next->updated_at->greaterThan($record->updated_at));
        $this->assertDatabaseCount('buyer_assessments', 1);
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
    }

    public function test_assessment_business_fields_are_optional_but_privacy_notice_is_required(): void
    {
        extract($this->scenario());
        $this->actingAs($agent)->put(route('reservations.assessment', $reservation), [])->assertSessionHasErrors('privacy_notice_confirmed', null, 'assessment');
        $this->put(route('reservations.assessment', $reservation), ['privacy_notice_confirmed' => 1])->assertRedirect();
        $record = $reservation->buyerAssessment()->firstOrFail();
        $this->assertSame(array_fill_keys(array_keys(BuyerAssessment::FACT_LABELS), null), $record->facts());
        $this->assertNull($record->agent_remarks);
        $this->assertNotNull($record->privacy_notice_given_at);
    }

    public function test_spot_cash_does_not_retain_or_validate_unnecessary_installment_information(): void
    {
        extract($this->scenario());
        $record = $this->assessment($agent, $reservation);
        $this->assessment($agent, $reservation, ['preferred_payment_method' => 'spot_cash', 'requested_installment_months' => ['unnecessary'], 'proposed_down_payment' => ['unnecessary']]);
        $this->assertNull($record->fresh()->requested_installment_months);
        $this->assertNull($record->fresh()->proposed_down_payment);
        // A saved assessment opens in read-only mode, not as an editable form.
        $summary = $this->actingAs($agent)
            ->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Edit Information');
        $summaryDocument = new DOMDocument;
        @$summaryDocument->loadHTML($summary->getContent());
        $this->assertSame(0, (new DOMXPath($summaryDocument))->query('//form[@data-assessment-form]')->length);

        // The installment fields are only rendered on the Edit Information screen.
        $response = $this->actingAs($agent)
            ->get(route('reservations.show', [$reservation, 'edit_assessment' => 1]))
            ->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@data-assessment-form]')->length);
        $this->assertSame(1, $xpath->query('//fieldset[@data-installment-fields and @hidden and @disabled]')->length);
        $this->assessment($agent, $reservation, ['preferred_payment_method' => null]);
        $this->assertNull($record->fresh()->requested_installment_months);
    }

    #[DataProvider('otherRoles')]
    public function test_other_roles_cannot_create_or_update_an_assessment(string $role): void
    {
        extract($this->scenario());
        $user = match ($role) {
            'buyer' => $buyer, 'unit_manager' => $manager, default => User::factory()->create(['role' => $role])
        };
        $this->actingAs($user)->put(route('reservations.assessment', $reservation), ['employment_status' => 'Forged', 'privacy_notice_confirmed' => 1])->assertForbidden();
        $this->assertDatabaseCount('buyer_assessments', 0);
    }

    public static function otherRoles(): array
    {
        return [['buyer'], ['unit_manager'], ['admin'], ['cashier'], ['field_planner']];
    }

    public function test_unrelated_agents_or_changed_lot_inquiry_assignments_cannot_write_assessments(): void
    {
        extract($this->scenario());
        $other = User::factory()->create(['role' => 'agent']);
        $payload = ['privacy_notice_confirmed' => 1];
        $this->actingAs($other)->put(route('reservations.assessment', $reservation), $payload)->assertForbidden();
        $this->get(route('reservations.show', $reservation))->assertForbidden();
        $lot->assigned_agent_id = $other->id;
        $lot->save();
        $this->actingAs($agent)->put(route('reservations.assessment', $reservation), $payload)->assertForbidden();
        $lot->assigned_agent_id = $agent->id;
        $lot->save();
        $inquiry->agent_id = $other->id;
        $inquiry->save();
        $this->put(route('reservations.assessment', $reservation), $payload)->assertForbidden();
        $this->assertDatabaseCount('buyer_assessments', 0);
    }

    public function test_unit_manager_reviews_assessment_and_contact_information_but_unrelated_manager_cannot(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $this->assessment($agent, $reservation);
        $this->actingAs($manager)->get(route('reservations.show', $reservation))->assertOk()->assertSee("Buyer's Contact Information", false)->assertSee('09123456789')->assertSee('Buyer Assessment')->assertSee('PHP 50,000–80,000')->assertSee('Internal remark: follow up privately.')->assertSee($agent->name)->assertDontSee('Save Assessment');
        $other = User::factory()->create(['role' => 'unit_manager']);
        $this->actingAs($other)->get(route('reservations.show', $reservation))->assertForbidden();
    }

    public function test_buyer_can_see_their_recorded_facts_but_internal_remarks_are_not_retrieved(): void
    {
        extract($this->scenario());
        $this->assessment($agent, $reservation);
        $this->actingAs($buyer)->get(route('reservations.show', $reservation))->assertOk()->assertSee('Facts Recorded by Your Agent')->assertSee('Self-employed')->assertSee('Request assessment fact correction')->assertDontSee('Internal remark: follow up privately.')->assertDontSee('Internal Agent assessment remarks')->assertDontSee('Save Assessment')
            ->assertViewHas('reservation', fn ($item) => ! array_key_exists('agent_remarks', $item->buyerAssessment->getAttributes()));
        $this->assertArrayNotHasKey('agent_remarks', $reservation->buyerAssessment()->first()->toArray());
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('reservations.show', $reservation))->assertForbidden();
    }

    public function test_admin_and_public_inventory_pages_do_not_expose_financial_assessments(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $this->assessment($agent, $reservation);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('reservations.show', $reservation))->assertOk()->assertSee('Buyer-provided address')->assertDontSee('Buyer Assessment')->assertDontSee('PHP 50,000–80,000')->assertDontSee('Internal remark: follow up privately.')->assertDontSee('Information Corrections')
            ->assertViewHas('reservation', fn ($item) => $item->buyerAssessment === null);
        // Admins may browse property listings but may not access the general
        // Reservations list under the final SGRE role permissions.
        foreach ([route('inventory.show', [$lot->property, 'status' => 'all']), route('inventory.lot', [$lot->property, $lot])] as $url) {
            $this->get($url)->assertOk()->assertDontSee('PHP 50,000–80,000')->assertDontSee('Internal remark: follow up privately.');
        }
        $this->get(route('reservations.index'))->assertForbidden();
    }

    public function test_agent_assessment_is_read_only_after_submission_editable_after_approval_and_locked_after_cts_completion(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $record = $this->assessment($agent, $reservation);
        $this->actingAs($agent)->get(route('reservations.show', $reservation))
            ->assertOk()->assertSee('Edit Information')->assertDontSee('data-assessment-form', false);
        $this->get(route('reservations.show', [$reservation, 'edit_assessment' => 1]))
            ->assertOk()->assertSee('Save Changes')->assertSee('Cancel')->assertSee('data-assessment-form', false);
        $this->approve($manager, $reservation);
        $this->actingAs($agent)->put(route('reservations.assessment', $reservation), [
            'employment_status' => 'Employed', 'privacy_notice_confirmed' => 1,
        ])->assertRedirect();
        $this->assertSame('Employed', $record->fresh()->employment_status);

        // Unit Manager approval now creates exactly one transaction and CTS.
        // Complete that existing record instead of inserting duplicates into the
        // unique transactions.reservation_id relationship.
        $transaction = $reservation->fresh()->transaction;
        $this->assertNotNull($transaction);
        $cts = $transaction->ctsRecord;
        $this->assertNotNull($cts);
        $this->assertSame($manager->id, $cts->initiated_by_id);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('cts_records', 1);

        $transaction->status = 'cts_completed';
        $transaction->save();
        $cts->status = \App\Enums\CtsStatus::Completed;
        $cts->completed_at = now();
        $cts->save();

        $this->actingAs($agent)->put(route('reservations.assessment', $reservation), [
            'employment_status' => 'Forged', 'privacy_notice_confirmed' => 1,
        ])->assertForbidden();
        $this->get(route('reservations.show', [$reservation, 'edit_assessment' => 1]))
            ->assertOk()->assertSee('Assessment information is read-only')->assertDontSee('data-assessment-form', false);
        $this->assertSame('Employed', $record->fresh()->employment_status);
    }

    public function test_missing_assessment_is_displayed_and_does_not_add_a_new_approval_requirement(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $this->actingAs($manager)->get(route('reservations.show', $reservation))->assertOk()->assertSee('No assessment has been recorded');
        $this->approve($manager, $reservation);
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertDatabaseCount('buyer_assessments', 0);
    }

    public function test_cancellation_after_initial_assessment_authorization_blocks_the_write(): void
    {
        extract($this->scenario());
        $changed = false;
        Gate::after(function ($user, $ability, $result) use ($reservation, &$changed) {
            if ($ability === 'updateAssessment' && $result === true && ! $changed) {
                $changed = true;
                Reservation::whereKey($reservation->id)->update(['status' => 'cancelled']);
            }
        });
        $this->actingAs($agent)->put(route('reservations.assessment', $reservation), ['employment_status' => 'Stale', 'privacy_notice_confirmed' => 1])->assertForbidden();
        $this->assertTrue($changed);
        $this->assertDatabaseCount('buyer_assessments', 0);
    }

    public function test_assessment_validation_and_malformed_old_input_are_safe(): void
    {
        extract($this->scenario());
        $invalid = ['employment_status' => ['bad'], 'source_of_income' => str_repeat('x', 256), 'monthly_income_range' => ['bad'], 'preferred_payment_method' => 'installment', 'requested_installment_months' => 0, 'proposed_down_payment' => '1.001', 'agent_remarks' => ['bad'], 'privacy_notice_confirmed' => 1];
        $this->actingAs($agent)->from(route('reservations.show', $reservation))->put(route('reservations.assessment', $reservation), $invalid)->assertSessionHasErrors(['employment_status', 'source_of_income', 'monthly_income_range', 'requested_installment_months', 'proposed_down_payment', 'agent_remarks'], null, 'assessment');
        $this->get(route('reservations.show', $reservation))->assertOk()->assertSee('Please check the following:');
        $this->assertDatabaseCount('buyer_assessments', 0);
        $this->actingAs($buyer)->from(route('reservations.show', $reservation))->put(route('reservations.details', $reservation), ['contact_number' => ['bad'], 'address' => ['bad'], 'notes' => ['bad']])->assertSessionHasErrors(['contact_number', 'address', 'notes'], null, 'contact');
        $this->get(route('reservations.show', $reservation))->assertOk()->assertSee('Please check the following:');
    }

    public function test_buyer_correction_does_not_modify_assessment_until_authorized_manager_applies_it(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $record = $this->assessment($agent, $reservation);
        $this->approve($manager, $reservation);
        $request = $this->correction($buyer, $reservation, ['requested_by' => $agent->id, 'reviewed_by' => $buyer->id, 'status' => 'approved']);
        $this->assertSame('Self-employed', $record->fresh()->employment_status);
        $this->assertSame('pending', $request->status);
        $this->assertSame($buyer->id, $request->requested_by);
        $this->assertNull($request->reviewed_by);
        $this->assertSame('Self-employed', $request->original_values['employment_status']);
        $this->assertArrayNotHasKey('agent_remarks', $request->original_values);
        $this->actingAs($manager)->get(route('reservations.show', $reservation))->assertOk()->assertSee('Approve Correction')->assertSee('Original')->assertSee('Proposed');
        $this->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'approve', 'review_notes' => 'Confirmed the corrected fact with the Buyer.'])->assertRedirect();
        $this->assertSame('Employed', $record->fresh()->employment_status);
        $this->assertSame($agent->id, $record->fresh()->recorded_by);
        $this->assertSame($manager->id, $record->fresh()->last_updated_by);
        $this->assertSame('Self-employed', $request->fresh()->original_values['employment_status']);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($manager->id, $request->fresh()->reviewed_by);
        $this->assertNotNull($request->fresh()->reviewed_at);
        $this->assertSame(ReservationStatus::Approved, $reservation->fresh()->status);
        $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'reserved']);
        $this->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'approve', 'review_notes' => 'Repeat'])->assertSessionHasErrors('correction');
    }

    public function test_approved_contact_can_be_corrected_only_by_request_and_retains_buyer_provenance(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $this->approve($manager, $reservation);
        $record = $reservation->buyerForm()->firstOrFail();
        $date = $record->submitted_at->toISOString();
        $request = $this->correction($buyer, $reservation, ['section' => 'contact', 'field' => 'address', 'proposed_value' => 'Corrected address']);
        $this->assertSame('Buyer-provided address', $record->fresh()->address);
        $this->actingAs($manager)->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'approve', 'review_notes' => 'Buyer confirmed new address.'])->assertRedirect();
        $this->assertSame('Corrected address', $record->fresh()->address);
        $this->assertSame($buyer->id, $record->fresh()->submitted_by);
        $this->assertSame($date, $record->fresh()->submitted_at->toISOString());
        $this->assertDatabaseCount('buyer_requirements', 1);
    }

    public function test_agent_can_request_an_approved_assessment_correction_but_not_contact_correction(): void
    {
        extract($this->scenario());
        $this->contact($buyer, $reservation);
        $this->assessment($agent, $reservation);
        $this->actingAs($agent)->post(route('reservations.corrections.store', $reservation), ['section' => 'assessment', 'field' => 'employment_status', 'proposed_value' => 'Employed', 'reason' => 'Correction'])->assertForbidden();
        $this->approve($manager, $reservation);
        $this->correction($agent, $reservation);
        $this->actingAs($agent)->post(route('reservations.corrections.store', $reservation), ['section' => 'contact', 'field' => 'address', 'proposed_value' => 'Forged', 'reason' => 'Correction'])->assertForbidden();
    }

    public function test_correction_privacy_authorization_and_internal_field_protection(): void
    {
        extract($this->scenario());
        $this->assessment($agent, $reservation);
        $payload = ['section' => 'assessment', 'field' => 'employment_status', 'proposed_value' => 'Employed', 'reason' => 'Correction'];
        foreach (['buyer', 'agent', 'admin', 'unit_manager', 'cashier', 'field_planner'] as $role) {
            $other = User::factory()->create(['role' => $role]);
            $this->actingAs($other)->post(route('reservations.corrections.store', $reservation), $payload)->assertForbidden();
        }
        $this->actingAs($buyer)->post(route('reservations.corrections.store', $reservation), array_replace($payload, ['field' => 'agent_remarks']))->assertSessionHasErrors('field', null, 'correction');
        $this->assertDatabaseCount('buyer_information_corrections', 0);
        $request = $this->correction($buyer, $reservation);
        foreach ([$buyer, $agent, User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'unit_manager'])] as $other) {
            $this->actingAs($other)->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'approve', 'review_notes' => 'Forged'])->assertForbidden();
        }
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_duplicate_pending_corrections_are_blocked_and_rejection_preserves_facts(): void
    {
        extract($this->scenario());
        $record = $this->assessment($agent, $reservation);
        $request = $this->correction($buyer, $reservation);
        $this->actingAs($buyer)->post(route('reservations.corrections.store', $reservation), ['section' => 'assessment', 'field' => 'employment_status', 'proposed_value' => 'Another', 'reason' => 'Duplicate'])->assertSessionHasErrors('correction');
        $this->actingAs($manager)->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'reject', 'review_notes' => 'Please confirm the proposed value.'])->assertRedirect();
        $this->assertSame('Self-employed', $record->fresh()->employment_status);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->correction($buyer, $reservation);
        $this->assertDatabaseCount('buyer_information_corrections', 2);
    }

    public function test_stale_corrections_cannot_overwrite_newer_facts(): void
    {
        extract($this->scenario());
        $record = $this->assessment($agent, $reservation);
        $request = $this->correction($buyer, $reservation);
        $this->assessment($agent, $reservation, ['employment_status' => 'Recently updated fact']);
        $this->actingAs($manager)->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'approve', 'review_notes' => 'Apply'])->assertSessionHasErrors('correction');
        $this->assertSame('Recently updated fact', $record->fresh()->employment_status);
        $this->assertSame('pending', $request->fresh()->status);
        $this->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'reject', 'review_notes' => 'Request a current correction.'])->assertRedirect();
    }

    public function test_payment_preference_correction_clears_installment_data_and_audits_all_changes(): void
    {
        extract($this->scenario());
        $record = $this->assessment($agent, $reservation);
        $request = $this->correction($buyer, $reservation, ['field' => 'preferred_payment_method', 'proposed_value' => 'Spot Cash']);
        $this->assertSame('150000.25', $request->original_values['proposed_down_payment']);
        $this->assertNull($request->proposed_values['proposed_down_payment']);
        $this->assertNull($request->proposed_values['requested_installment_months']);
        $this->actingAs($manager)->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'approve', 'review_notes' => 'Buyer confirmed spot cash.'])->assertRedirect();
        $this->assertSame('spot_cash', $record->fresh()->preferred_payment_method);
        $this->assertNull($record->fresh()->proposed_down_payment);
        $this->assertNull($record->fresh()->requested_installment_months);
    }

    public function test_correction_validation_prevents_bad_values_missing_records_and_wrong_parent_ids(): void
    {
        extract($this->scenario());
        $payload = ['section' => 'assessment', 'field' => 'employment_status', 'proposed_value' => 'Employed', 'reason' => 'Correction'];
        $this->actingAs($buyer)->post(route('reservations.corrections.store', $reservation), $payload)->assertSessionHasErrors('correction');
        $this->assessment($agent, $reservation);
        $this->actingAs($buyer)->post(route('reservations.corrections.store', $reservation), array_replace($payload, ['field' => 'proposed_down_payment', 'proposed_value' => '-1']))->assertSessionHasErrors('proposed_value', null, 'correction');
        $this->post(route('reservations.corrections.store', $reservation), array_replace($payload, ['proposed_value' => ['bad']]))->assertSessionHasErrors('proposed_value', null, 'correction');
        $request = $this->correction($buyer, $reservation);
        $this->actingAs($manager)->patch(route('reservations.corrections.review', [$reservation, $request]), ['decision' => 'approve'])->assertSessionHasErrors('review_notes', null, 'correctionReview');
        $second = $this->scenario();
        $this->actingAs($second['manager'])->patch(route('reservations.corrections.review', [$second['reservation'], $request]), ['decision' => 'approve', 'review_notes' => 'Wrong record'])->assertNotFound();
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_database_limits_pending_corrections_independently_of_forms(): void
    {
        extract($this->scenario());
        $this->assessment($agent, $reservation);
        $request = $this->correction($buyer, $reservation);
        $copy = $request->replicate();
        $this->expectException(UniqueConstraintViolationException::class);
        $copy->save();
    }

    public function test_database_allows_only_one_assessment_per_reservation(): void
    {
        extract($this->scenario());
        $assessment = $this->assessment($agent, $reservation);
        $this->expectException(UniqueConstraintViolationException::class);
        $assessment->replicate()->save();
    }

    public function test_guests_and_unverified_agents_cannot_save_assessments_or_corrections(): void
    {
        extract($this->scenario());
        $this->post('/logout');
        $this->put(route('reservations.assessment', $reservation), [])->assertRedirect(route('login'));
        $this->post(route('reservations.corrections.store', $reservation), [])->assertRedirect(route('login'));
        $agent->email_verified_at = null;
        $agent->save();
        $this->actingAs($agent)->put(route('reservations.assessment', $reservation), [])->assertRedirect(route('verification.notice'));
        $this->post(route('reservations.corrections.store', $reservation), [])->assertRedirect(route('verification.notice'));
    }

    public function test_assessment_and_correction_text_is_escaped_and_internal_remarks_stay_private(): void
    {
        extract($this->scenario());
        $unsafe = '<script>alert("private")</script>';
        $this->assessment($agent, $reservation, ['employment_status' => $unsafe, 'agent_remarks' => '<script>secret()</script>']);
        $this->actingAs($manager)->get(route('reservations.show', $reservation))->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false);
        $this->correction($buyer, $reservation, ['proposed_value' => '<img src=x onerror=alert(1)>', 'reason' => $unsafe]);
        $this->get(route('reservations.show', $reservation))->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false)->assertDontSee('secret()', false);
    }
}
