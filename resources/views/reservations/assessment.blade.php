@can('viewAssessmentFacts', $reservation)
    @php
        $assessment = $reservation->buyerAssessment;
        $internal = auth()->user()->can('viewAssessment', $reservation);
        $editable = auth()->user()->can('updateAssessment', $reservation);
    @endphp
    <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
        <h2 class="font-semibold text-emerald-950">{{ $internal ? 'Buyer Assessment' : 'Facts Recorded by Your Agent' }}</h2>
        <p class="mt-2 text-sm leading-6 text-stone-600">Buyer-provided facts collected during conversations and recorded by the assigned Agent. These are proposed preferences, not approved payment terms.</p>
        @if($assessment)
            <p class="mt-3 text-xs leading-5 text-stone-500">Recorded by {{ $assessment->recordedBy->name }} · Last updated {{ $assessment->updated_at->format('M j, Y, g:i A') }} by {{ $assessment->lastUpdatedBy->name }}</p>
        @else
            <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">No assessment has been recorded. Employment, income, and payment preferences have not been provided.</p>
        @endif
        @if($editable)
            <form method="POST" action="{{ route('reservations.assessment', $reservation) }}" class="mt-5 space-y-5" data-assessment-form>
                @csrf @method('PUT')
                <x-form-errors bag="assessment" />
                <p class="rounded-xl bg-stone-50 p-4 text-xs leading-5 text-stone-600">Explain to the Buyer that these details are recorded for reservation review, their assigned Agent and Unit Manager can access them, and factual corrections can be requested here. Collect only relevant information. All assessment fields below are optional.</p>
                <fieldset class="grid gap-4 sm:grid-cols-2"><legend class="mb-4 font-medium text-emerald-950">Facts reported by the Buyer</legend>
                    <x-form-field name="employment_status" label="Employment status (optional)" :value="$assessment?->employment_status" :required="false" error-bag="assessment" maxlength="120" />
                    <x-form-field name="source_of_income" label="Source of income (optional)" :value="$assessment?->source_of_income" :required="false" error-bag="assessment" maxlength="255" />
                    <x-form-field name="monthly_income_range" label="Approximate monthly income range (optional)" :value="$assessment?->monthly_income_range" :required="false" error-bag="assessment" maxlength="120" />
                    <x-select-field name="preferred_payment_method" label="Preferred payment method (optional)" error-bag="assessment" data-payment-preference>
                        <option value="">Not provided</option><option value="spot_cash" @selected(old('preferred_payment_method', $assessment?->preferred_payment_method) === 'spot_cash')>Spot Cash</option><option value="installment" @selected(old('preferred_payment_method', $assessment?->preferred_payment_method) === 'installment')>Installment</option>
                    </x-select-field>
                </fieldset>
                <fieldset class="grid gap-4 sm:grid-cols-2" data-installment-fields @if(old('preferred_payment_method', $assessment?->preferred_payment_method) !== 'installment') hidden disabled @endif><legend class="mb-4 text-sm font-medium text-stone-600">For an installment preference only</legend>
                    <x-form-field name="requested_installment_months" label="Requested duration in months (optional)" type="number" :value="$assessment?->requested_installment_months" :required="false" error-bag="assessment" min="1" max="2147483647" step="1" />
                    <x-form-field name="proposed_down_payment" label="Proposed down payment in PHP (optional)" type="number" :value="$assessment?->proposed_down_payment" :required="false" error-bag="assessment" min="0" max="9999999999999.99" step="0.01" />
                </fieldset>
                <noscript><p class="text-xs text-stone-500">Save an Installment preference first to enter installment details.</p></noscript>
                <x-textarea-field name="additional_clarifications" label="Buyer requirements or clarifications (optional)" :value="$assessment?->additional_clarifications" error-bag="assessment" rows="3" maxlength="4000" />
                <div class="border-t border-stone-100 pt-5"><x-textarea-field name="agent_remarks" label="Internal Agent assessment remarks (optional)" :value="$assessment?->agent_remarks" error-bag="assessment" rows="4" maxlength="4000" /><p class="mt-2 text-xs text-stone-500">Visible only to the assigned Agent and authorized Unit Manager. Keep Buyer-reported facts in the fields above.</p></div>
                <label class="flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="privacy_notice_confirmed" value="1" class="mt-1 size-4 accent-emerald-800" required @checked(old('privacy_notice_confirmed') === '1')><span>I explained the privacy notice and correction process to the Buyer.</span></label>
                <button class="btn-primary">Save Assessment</button>
            </form>
        @elseif($assessment)
            <dl class="mt-5 grid gap-4 break-words text-sm sm:grid-cols-2">@foreach(\App\Models\BuyerAssessment::FACT_LABELS as $field => $label)<div><dt class="font-medium text-stone-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap"><x-information-value :field="$field" :value="$assessment->getAttribute($field)" /></dd></div>@endforeach</dl>
            @if($internal)<div class="mt-5 border-t border-stone-100 pt-5"><h3 class="text-sm font-medium text-stone-500">Internal Agent assessment remarks</h3><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $assessment->agent_remarks ?: 'Not provided' }}</p><p class="mt-3 text-xs text-stone-500">Privacy notice confirmed {{ $assessment->privacy_notice_given_at->format('M j, Y, g:i A') }}.</p></div>@endif
            @if($reservation->status !== \App\Enums\ReservationStatus::Pending)<p class="mt-4 text-xs text-stone-500">Normal assessment editing is locked. Factual corrections require a recorded Unit Manager decision.</p>@endif
        @endif
    </section>
@endcan
