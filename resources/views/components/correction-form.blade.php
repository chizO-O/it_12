@props(['reservation', 'section', 'labels'])
@php($prefix = $section.'-correction')
<details class="mt-4 rounded-xl border border-stone-200 p-4">
    <summary class="cursor-pointer text-sm font-semibold text-emerald-900">Request {{ $section === 'contact' ? 'contact information' : 'assessment fact' }} correction</summary>
    <form method="POST" action="{{ route('reservations.corrections.store', $reservation) }}" class="mt-4 space-y-4">
        @csrf
        <input type="hidden" name="section" value="{{ $section }}">
        @if(old('section') === $section)<x-form-errors bag="correction" />@endif
        <div><label for="{{ $prefix }}-field" class="mb-2 block text-sm font-medium">Information to correct</label><select id="{{ $prefix }}-field" name="field" class="form-input" required><option value="">Choose a field</option>@foreach($labels as $field => $label)<option value="{{ $field }}" @selected(old('section') === $section && old('field') === $field)>{{ $label }}</option>@endforeach</select></div>
        @php($proposed = old('section') === $section ? old('proposed_value') : '')
        <div><label for="{{ $prefix }}-value" class="mb-2 block text-sm font-medium">Corrected information</label><textarea id="{{ $prefix }}-value" name="proposed_value" rows="3" maxlength="4000" class="form-input">{{ is_string($proposed) ? $proposed : '' }}</textarea><p class="mt-2 text-xs leading-5 text-stone-500">Leave blank only to remove optional information.@if($section === 'assessment') For payment method, enter Spot Cash or Installment. Switching to Spot Cash removes installment duration and down payment.@endif</p></div>
        @php($reason = old('section') === $section ? old('reason') : '')
        <div><label for="{{ $prefix }}-reason" class="mb-2 block text-sm font-medium">Reason for correction</label><textarea id="{{ $prefix }}-reason" name="reason" rows="3" maxlength="2000" class="form-input" required>{{ is_string($reason) ? $reason : '' }}</textarea></div>
        <p class="text-xs leading-5 text-stone-500">A Unit Manager must review this request. Reasons and review decisions are visible to the Buyer; keep internal Agent remarks out of this request.</p>
        <button type="submit" class="btn-secondary">Submit Correction Request</button>
    </form>
</details>
