@php
    $reservation = $cts->transaction->reservation;
    $role = auth()->user()->role;
@endphp
<x-workspace :title="'CTS for Lot '.$reservation->lot->lot_no">
    <a href="{{ route('cts.index') }}" class="text-link text-sm">← Back to CTS (Contract to Sell)</a>
    <div class="my-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">{{ $reservation->lot->property->location }} · {{ $reservation->lot->property->name }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-emerald-950">Contract to Sell — Lot {{ $reservation->lot->lot_no }}</h1>
            <p class="mt-2 text-sm text-stone-600">Buyer: {{ $reservation->buyer->name }} · Assigned Agent: {{ $reservation->agent->name }}</p>
        </div>
        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900">{{ $cts->status->label() }}</span>
    </div>
    <x-feedback />
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Contract preparation status</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Reservation</dt><dd>#{{ $reservation->id }} · {{ \App\Support\ReservationProgress::label($reservation) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Initiated</dt><dd>{{ $cts->created_at->format('M j, Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Property verified</dt><dd>{{ $cts->property_verified_at?->format('M j, Y') ?? 'Pending' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Draft version</dt><dd>{{ $cts->draft_version ?: 'Not uploaded' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Buyer review</dt><dd>{{ $cts->buyer_reviewed_at?->format('M j, Y') ?? 'Pending' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Executed document</dt><dd>{{ $cts->executed_uploaded_at ? 'Recorded' : 'Pending' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Notarized on</dt><dd>{{ $cts->notarized_at?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
            </dl>
            @if($cts->buyer_feedback)
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm"><strong>Buyer feedback:</strong> {{ $cts->buyer_feedback }}</p>
            @endif
            <a href="{{ route('reservations.show', $reservation) }}" class="text-link mt-5 inline-flex">View reservation →</a>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">CTS documents</h2>
            <p class="mt-2 text-xs text-stone-500">The latest draft and final signed/notarized Contract to Sell are stored privately. Links are temporary; access is checked per Buyer and authorized employee. Do not share the link.</p>
            @can('viewDocument', $cts)
                <div class="mt-5 flex flex-col gap-3">
                    @if($cts->draft_path)
                        <a class="btn-secondary" href="{{ route('cts.document', [$cts, 'draft']) }}" target="_blank" rel="noopener noreferrer">Open latest CTS draft (PDF)</a>
                    @else
                        <p class="text-sm text-stone-500">No draft uploaded yet.</p>
                    @endif
                    @if($cts->executed_path)
                        <a class="btn-secondary" href="{{ route('cts.document', [$cts, 'executed']) }}" target="_blank" rel="noopener noreferrer">Open executed CTS (PDF)</a>
                    @endif
                </div>
            @else
                <p class="mt-4 text-sm text-stone-500">Document viewing is restricted for your role.</p>
            @endcan
            <p class="mt-5 rounded-xl bg-stone-50 p-4 text-xs leading-5 text-stone-600">Buyer review confirms that the draft has been read. It does not replace physical signing, notarization, or property-title transfer.</p>
        </section>
    </div>

    {{-- Contract facts shown to the buyer and the authorized office, sourced from this lot's records. --}}
    <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-emerald-950">Contract to Sell — information for this lot</h2>
        <p class="mt-2 text-sm text-stone-600">This overview helps you identify the correct lot and contract. The signed/notarized PDF is the authoritative agreement, not this on-screen summary.</p>
        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-medium text-stone-500">Buyer</dt><dd class="mt-1">{{ $reservation->buyer->name }}</dd></div>
            <div><dt class="font-medium text-stone-500">Seller / Landowner</dt><dd class="mt-1">{{ $cts->landowner_name ?: 'Awaiting Field Planner verification' }}</dd></div>
            @foreach(['buyer_permanent_address' => 'Buyer permanent address', 'buyer_marital_status' => 'Buyer marital status', 'buyer_age' => 'Buyer age', 'buyer_nationality' => 'Buyer nationality', 'landowner_permanent_address' => 'Seller permanent address', 'landowner_marital_status' => 'Seller marital status', 'landowner_age' => 'Seller age', 'landowner_nationality' => 'Seller nationality'] as $field => $label)
                <div><dt class="font-medium text-stone-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $cts->getAttribute($field) ?: 'Not submitted yet' }}</dd></div>
            @endforeach
            <div><dt class="font-medium text-stone-500">Property</dt><dd class="mt-1">{{ $reservation->lot->property->name }} ({{ $reservation->lot->property->location }})</dd></div>
            <div><dt class="font-medium text-stone-500">Lot / Block</dt><dd class="mt-1">{{ $reservation->lot->lot_no }} / {{ $reservation->lot->block_no ?: 'None' }}</dd></div>
            <div><dt class="font-medium text-stone-500">Lot area</dt><dd class="mt-1">{{ number_format((float) $reservation->lot->area_sqm, 2) }} sqm</dd></div>
            <div><dt class="font-medium text-stone-500">Contract price</dt><dd class="mt-1">{{ $cts->contract_price !== null ? '₱'.number_format((float)$cts->contract_price, 2) : 'Not recorded by Secretary yet' }}</dd></div>
            @if($reservation->buyerForm)
                <div><dt class="font-medium text-stone-500">Buyer contact number</dt><dd class="mt-1">{{ $reservation->buyerForm->contact_number ?: 'Not submitted' }}</dd></div>
                <div><dt class="font-medium text-stone-500">Buyer address</dt><dd class="mt-1 whitespace-pre-wrap">{{ $reservation->buyerForm->address ?: 'Not submitted' }}</dd></div>
            @endif
            <div class="sm:col-span-2"><dt class="font-medium text-stone-500">Land ownership / transfer agreement</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $cts->ownership_agreement ?: 'Awaiting Secretary preparation' }}</dd></div>
            <div class="sm:col-span-2"><dt class="font-medium text-stone-500">Payment / purchase agreement</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $cts->payment_agreement ?: 'Awaiting Secretary preparation' }}</dd></div>
        </dl>
        @if($cts->status !== \App\Enums\CtsStatus::Completed)
            <p class="mt-5 rounded-xl bg-amber-50 p-3 text-xs text-amber-900">Preparation in progress. This information may change during Buyer review. It is not a final signed contract.</p>
        @endif
    </section>

    @php
        $buyerCanEdit = auth()->user()->can('editBuyerDetails', $cts);
        $buyerEditing = $buyerCanEdit && (! $cts->buyer_details_submitted_at || request()->boolean('edit_buyer_details') || $errors->has('buyer_full_name') || $errors->has('buyer_permanent_address') || $errors->has('buyer_age') || $errors->has('buyer_marital_status') || $errors->has('buyer_nationality'));
    @endphp
    @if($role === \App\Enums\UserRole::Buyer)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Buyer's Details — Contract to Sell</h2>
            <p class="mt-2 text-sm text-stone-600">Enter your legal name as it should appear on this lot's Contract to Sell and submit the remaining required details. Only authorized CTS participants can view these details.</p>
            @if($buyerEditing)
                <form method="POST" action="{{ route('cts.buyer-details', $cts) }}" class="mt-5 space-y-4">
                    @csrf @method('PATCH')
                    <x-form-errors />
                    <x-form-field name="buyer_full_name" label="Buyer legal full name" :value="$cts->buyer_full_name ?: $reservation->buyer->name" maxlength="200" />
                    <x-textarea-field name="buyer_permanent_address" label="Permanent address" :value="$cts->buyer_permanent_address" :required="true" rows="2" maxlength="1500" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="buyer_marital_status" class="mb-2 block text-sm font-medium">Marital status</label><select id="buyer_marital_status" name="buyer_marital_status" class="form-input w-full" required><option value="">Select status</option>@foreach(['Single','Married','Widowed','Legally Separated','Divorced','Other'] as $status)<option value="{{ $status }}" @selected(old('buyer_marital_status', $cts->buyer_marital_status) === $status)>{{ $status }}</option>@endforeach</select></div>
                        <x-form-field name="buyer_age" label="Age" type="number" :value="$cts->buyer_age" min="1" max="120" />
                        <x-form-field name="buyer_nationality" label="Nationality" :value="$cts->buyer_nationality" maxlength="100" />
                    </div>
                    <div class="flex gap-3"><button class="btn-primary" type="submit">{{ $cts->buyer_details_submitted_at ? 'Save Changes' : 'Submit Buyer Details' }}</button>@if($cts->buyer_details_submitted_at)<a class="btn-secondary" href="{{ route('cts.show', $cts) }}">Cancel</a>@endif</div>
                </form>
            @elseif($cts->buyer_details_submitted_at)
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    @foreach(['buyer_full_name' => 'Legal full name', 'buyer_permanent_address' => 'Permanent address', 'buyer_marital_status' => 'Marital status', 'buyer_age' => 'Age', 'buyer_nationality' => 'Nationality'] as $field => $label)
                        <div><dt class="font-medium text-stone-500">{{ $label }}</dt><dd class="mt-1">{{ $cts->getAttribute($field) }}</dd></div>
                    @endforeach
                </dl>
                @if($buyerCanEdit)<a class="btn-secondary mt-5 inline-flex" href="{{ route('cts.show', [$cts, 'edit_buyer_details' => 1]) }}">Edit Information</a>
                @else<p class="mt-4 text-sm text-stone-500">CTS is completed. Buyer details are locked.</p>@endif
            @else
                <p class="mt-4 text-sm text-stone-500">These details are locked after CTS completion.</p>
            @endif
        </section>
    @endif

    @if($role === \App\Enums\UserRole::Admin)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Buyer and Seller — CTS compilation checklist</h2>
            <p class="mt-2 text-sm text-stone-600">The Secretary can prepare the first draft only after both parties provide complete CTS details. Buyer and Field Planner submit them independently.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border p-4">
                    <p class="font-semibold {{ $cts->buyer_details_submitted_at ? 'text-emerald-800' : 'text-amber-800' }}">Buyer: {{ $cts->buyer_details_submitted_at ? 'Submitted' : 'Awaiting submission' }}</p>
                    @if($cts->buyer_details_submitted_at)
                        <dl class="mt-3 space-y-2 text-sm">
                            @foreach(['buyer_full_name' => 'Full name', 'buyer_permanent_address' => 'Permanent address', 'buyer_marital_status' => 'Marital status', 'buyer_age' => 'Age', 'buyer_nationality' => 'Nationality'] as $field => $label)
                                <div><dt class="text-stone-500">{{ $label }}</dt><dd class="break-words">{{ $cts->getAttribute($field) ?: 'Not recorded' }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </div>
                <div class="rounded-xl border p-4">
                    <p class="font-semibold {{ $cts->property_verified_at ? 'text-emerald-800' : 'text-amber-800' }}">Seller / Field Planner: {{ $cts->property_verified_at ? 'Submitted' : 'Awaiting verification' }}</p>
                    @if($cts->property_verified_at)
                        <dl class="mt-3 space-y-2 text-sm">
                            @foreach(['landowner_name' => 'Full name', 'landowner_permanent_address' => 'Permanent address', 'landowner_marital_status' => 'Marital status', 'landowner_age' => 'Age', 'landowner_nationality' => 'Nationality'] as $field => $label)
                                <div><dt class="text-stone-500">{{ $label }}</dt><dd class="break-words">{{ $cts->getAttribute($field) ?: 'Not recorded' }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </div>
            @unless($cts->partiesReady())
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Waiting for complete buyer and seller details. CTS drafting is locked until both are ready.</p>
            @endunless
        </section>
    @endif

    @can('prepare', $cts)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Secretary/Admin — prepare contract information</h2>
            <p class="mt-2 text-sm text-stone-600">The buyer and lot are already linked from the approved reservation. Enter only the contract price and agreed terms verified outside GroveMap. Do not invent legal clauses.</p>
            <form method="POST" action="{{ route('cts.prepare', $cts) }}" class="mt-5 space-y-4">
                @csrf @method('PATCH')
                <x-form-errors />
                <x-form-field name="contract_price" label="Agreed contract price (PHP)" type="number" step="0.01" min="0.01" :value="$cts->contract_price" />
                <x-textarea-field name="ownership_agreement" label="Land ownership / title transfer agreement" :value="$cts->ownership_agreement" rows="4" maxlength="6000" />
                <x-textarea-field name="payment_agreement" label="Payment / purchase agreement" :value="$cts->payment_agreement" rows="4" maxlength="6000" />
                <label class="flex gap-3 text-sm"><input type="checkbox" name="terms_verified" value="1" required class="mt-1"><span>I checked that the entered details reflect the terms agreed by the relevant parties outside GroveMap.</span></label>
                <button class="btn-primary" type="submit">{{ $cts->contract_prepared_at ? 'Save CTS changes' : 'Save CTS information' }}</button>
            </form>
        </section>
    @endcan

    @php
        $verified = (bool) $cts->property_verified_at;
        $canEditLandowner = auth()->user()->can('verifyProperty', $cts);
        $editingLandowner = $canEditLandowner && (! $verified || request()->boolean('edit_landowner') || $errors->any());
        $canReadLandowner = in_array($role, [\App\Enums\UserRole::FieldPlanner, \App\Enums\UserRole::UnitManager, \App\Enums\UserRole::Admin], true);
    @endphp
    @if($canReadLandowner)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Landowner Verification Details</h2>
            <p class="mt-2 text-sm text-stone-600">The Field Planner verifies the seller/landowner directly outside GroveMap. These are confidential contact details, not proof of legal title.</p>
            @if($editingLandowner)
                <form method="POST" action="{{ route('cts.verify', $cts) }}" class="mt-5 space-y-4">
                    @csrf @method('PATCH')
                    <x-form-errors />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form-field name="landowner_name" label="Seller / Landowner full name" :value="$cts->landowner_name" maxlength="200" />
                        <x-form-field name="landowner_contact" label="Contact number" :value="$cts->landowner_contact" type="tel" minlength="7" maxlength="40" />
                    </div>
                    <x-textarea-field name="landowner_address" label="Current address" :value="$cts->landowner_address" rows="3" :required="true" maxlength="1500" />
                    <x-textarea-field name="landowner_permanent_address" label="Permanent address" :value="$cts->landowner_permanent_address" rows="2" :required="true" maxlength="1500" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="landowner_marital_status" class="mb-2 block text-sm font-medium">Marital status</label><select name="landowner_marital_status" id="landowner_marital_status" class="form-input w-full" required><option value="">Select status</option>@foreach(['Single','Married','Widowed','Legally Separated','Divorced','Other'] as $status)<option value="{{ $status }}" @selected(old('landowner_marital_status', $cts->landowner_marital_status) === $status)>{{ $status }}</option>@endforeach</select></div>
                        <x-form-field name="landowner_age" label="Age" type="number" :value="$cts->landowner_age" min="1" max="120" />
                        <x-form-field name="landowner_nationality" label="Nationality" :value="$cts->landowner_nationality" maxlength="100" />
                    </div>
                    <x-form-field name="landowner_relationship" label="Ownership / relationship to property (optional)" :value="$cts->landowner_relationship" :required="false" maxlength="150" />
                    <x-textarea-field name="verification_notes" label="Verification details and remarks (optional)" :value="$cts->verification_notes" rows="3" :required="false" maxlength="3000" />
                    <label class="flex gap-3 text-sm"><input class="mt-1" type="checkbox" name="verified" value="1" required><span>I verified the property and landowner information outside the application.</span></label>
                    <div class="flex flex-wrap gap-3">
                        <button class="btn-primary">{{ $verified ? 'Save Changes' : 'Submit Landowner Information' }}</button>
                        @if($verified)<a class="btn-secondary" href="{{ route('cts.show', $cts) }}">Cancel</a>@endif
                    </div>
                </form>
            @elseif($verified)
                <dl class="mt-5 grid gap-4 break-words text-sm sm:grid-cols-2">
                    @foreach(['landowner_name' => 'Seller / Landowner full name', 'landowner_contact' => 'Contact number', 'landowner_address' => 'Current address', 'landowner_permanent_address' => 'Permanent address', 'landowner_marital_status' => 'Marital status', 'landowner_age' => 'Age', 'landowner_nationality' => 'Nationality', 'landowner_relationship' => 'Ownership / relationship to property', 'verification_notes' => 'Verification remarks'] as $field => $label)
                        <div><dt class="font-medium text-stone-500">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $cts->getAttribute($field) ?: 'Not recorded' }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-stone-500">Verified on {{ $cts->property_verified_at->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A').' PHT' }}. @if(!$cts->landowner_name) Older verification record: structured details have not been filled in yet. @endif</p>
                @if($canEditLandowner)
                    <a class="btn-secondary mt-5 inline-flex" href="{{ route('cts.show', [$cts, 'edit_landowner' => 1]) }}">Edit Information</a>
                @else
                    <p class="mt-4 text-xs text-stone-500">This record is read-only. Once CTS is completed, amendments are locked.</p>
                @endif
            @else
                <p class="mt-4 text-sm text-amber-900">Awaiting Field Planner verification.</p>
            @endif
        </section>
    @endif

    @can('uploadDraft', $cts)
        @if($cts->draft_path || $cts->partiesReady())
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Secretary/Admin — upload CTS draft</h2>
            <p class="mt-2 text-sm text-stone-600">Upload the externally prepared Contract to Sell PDF (maximum 10 MB) after saving the agreed terms and after the Field Planner verifies the seller. A revised PDF is required if the Buyer requests changes.</p>
            <form method="POST" action="{{ route('cts.draft', $cts) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <label for="draft" class="block text-sm font-medium">CTS draft PDF</label>
                <input id="draft" type="file" name="document" accept="application/pdf,.pdf" required class="form-input w-full">
                <button class="btn-primary">Upload and send for Buyer review</button>
            </form>
        </section>
        @endif
    @endcan

    @can('review', $cts)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Buyer — review CTS draft</h2>
            <p class="mt-2 text-sm text-stone-600">Read the complete draft above. You may acknowledge reviewing it or request corrections through the office. Acknowledgment is not a legal signature.</p>
            <form method="POST" action="{{ route('cts.review', $cts) }}" class="mt-4 space-y-4">
                @csrf @method('PATCH')
                <label for="buyer_feedback" class="block text-sm font-medium">Requested changes or remarks (required for corrections)</label>
                <textarea id="buyer_feedback" name="feedback" rows="3" maxlength="3000" class="form-input w-full">{{ old('feedback') }}</textarea>
                <div class="flex flex-wrap gap-3">
                    <button class="btn-primary" type="submit" name="decision" value="acknowledge">Acknowledge draft review</button>
                    <button class="btn-secondary" type="submit" name="decision" value="request_revision">Request revisions</button>
                </div>
            </form>
        </section>
    @endcan

    @can('complete', $cts)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Secretary/Admin — record executed CTS</h2>
            <p class="mt-2 text-sm text-stone-600">Only after all required parties have signed and the CTS has been notarized outside GroveMap. Attach the executed PDF and the actual notarization date.</p>
            <form method="POST" action="{{ route('cts.complete', $cts) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <label for="executed" class="block text-sm font-medium">Signed and notarized CTS PDF</label>
                <input id="executed" type="file" name="document" accept="application/pdf,.pdf" required class="form-input w-full">
                <label for="notarized_at" class="block text-sm font-medium">Date notarized</label>
                <input id="notarized_at" type="date" name="notarized_at" value="{{ old('notarized_at') }}" max="{{ now()->toDateString() }}" required class="form-input w-full">
                <label class="flex gap-3 text-sm"><input class="mt-1" type="checkbox" name="external_process_confirmed" value="1" required><span>I confirm required signing and notarization were completed outside GroveMap.</span></label>
                <button class="btn-primary">Record executed CTS</button>
            </form>
        </section>
    @endcan
    @if($cts->status === \App\Enums\CtsStatus::Completed)
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            The final Contract to Sell has been recorded and is available to the Buyer. <a class="text-link ml-1" href="{{ route('sales.show', $cts->transaction) }}">View sale and payment tracking →</a>
        </div>
    @endif
</x-workspace>
