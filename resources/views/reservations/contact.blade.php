@php
    $contact = $reservation->buyerForm;
    $canEditContact = auth()->user()->can('updateContact', $reservation);
    $editingContact = $canEditContact && (! $contact || request()->boolean('edit_contact') || $errors->getBag('contact')->any());
@endphp
<section class="rounded-2xl border border-stone-200 bg-white p-6">
    <h2 class="font-semibold text-emerald-950">Buyer's Contact Information</h2>
    <p class="mt-2 text-xs leading-5 text-stone-500">Submitted directly by the Buyer. Include contact details only; your Agent records assessment information separately.</p>
    @if($editingContact)
        <form action="{{ route('reservations.details', $reservation) }}" method="POST" class="mt-5 space-y-4">
            @csrf @method('PUT')
            <x-form-errors bag="contact" />
            <x-form-field name="contact_number" label="Contact number" :value="$contact?->contact_number" error-bag="contact" type="tel" minlength="7" maxlength="30" autocomplete="tel" />
            <x-textarea-field name="address" label="Current address" :value="$contact?->address" error-bag="contact" :required="true" rows="3" maxlength="1000" autocomplete="street-address" />
            <x-textarea-field name="notes" label="Additional contact details (optional)" :value="$contact?->notes" error-bag="contact" rows="3" maxlength="2000" />
            <div class="flex flex-wrap gap-3"><button class="btn-primary" type="submit">{{ $contact ? 'Save Changes' : 'Submit Contact Information' }}</button>@if($contact)<a class="btn-secondary" href="{{ route('reservations.show', $reservation) }}">Cancel</a>@endif</div>
        </form>
    @elseif($contact)
        <dl class="mt-4 space-y-3 break-words text-sm">
            @foreach(\App\Models\BuyerInformationCorrection::CONTACT_LABELS as $field => $label)<div><dt class="font-medium text-stone-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap"><x-information-value :field="$field" :value="$contact->getAttribute($field)" /></dd></div>@endforeach
        </dl>
        <p class="mt-4 text-xs text-stone-500">Submitted {{ $contact->submitted_at->format('M j, Y, g:i A') }} · Last updated {{ $contact->updated_at->format('M j, Y, g:i A') }}</p>
        @if($canEditContact)<a class="btn-secondary mt-5" href="{{ route('reservations.show', [$reservation, 'edit_contact' => 1]) }}">Edit Information</a>@else<p class="mt-4 text-xs text-stone-500">Contact information is read-only. Corrections require Unit Manager review.</p>@endif
    @else
        <p class="mt-4 text-sm text-amber-900">Contact information has not been submitted.</p>
    @endif
</section>
