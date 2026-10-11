@php
    $reservation = $cts->transaction->reservation;
    $role = auth()->user()->role;
@endphp
<x-workspace :title="'CTS for Lot '.$reservation->lot->lot_no">
    <a href="{{ route('cts.index') }}" class="text-link text-sm">← Back to CTS tracking</a>
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
            <h2 class="font-semibold text-emerald-950">CTS progress</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Reservation</dt><dd>#{{ $reservation->id }} · {{ $reservation->status->label() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Initiated</dt><dd>{{ $cts->created_at->format('M j, Y') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Property verified</dt><dd>{{ $cts->property_verified_at?->format('M j, Y') ?? 'Pending' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Draft version</dt><dd>{{ $cts->draft_version ?: 'Not uploaded' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Buyer review</dt><dd>{{ $cts->buyer_reviewed_at?->format('M j, Y') ?? 'Pending' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Executed document</dt><dd>{{ $cts->executed_uploaded_at ? 'Recorded' : 'Pending' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-stone-500">Notarized on</dt><dd>{{ $cts->notarized_at?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
            </dl>
            @if($cts->verification_notes && $role !== \App\Enums\UserRole::Buyer)
                <p class="mt-4 rounded-lg bg-stone-50 p-3 text-sm"><strong>Field Planner notes:</strong> {{ $cts->verification_notes }}</p>
            @endif
            @if($cts->buyer_feedback)
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm"><strong>Buyer feedback:</strong> {{ $cts->buyer_feedback }}</p>
            @endif
            <a href="{{ route('reservations.show', $reservation) }}" class="text-link mt-5 inline-flex">View reservation →</a>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Private CTS documents</h2>
            <p class="mt-2 text-xs text-stone-500">Only authorized participants may request a short-lived link to these documents. Do not share the link.</p>
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

    @can('verifyProperty', $cts)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Field Planner — property verification</h2>
            <p class="mt-2 text-sm text-stone-600">Confirm the property and owner details with the landowner outside GroveMap before enabling the CTS draft upload.</p>
            <form method="POST" action="{{ route('cts.verify', $cts) }}" class="mt-4 space-y-4">
                @csrf @method('PATCH')
                <label class="block text-sm font-medium" for="verification_notes">Verified details and remarks</label>
                <textarea id="verification_notes" name="verification_notes" rows="4" maxlength="3000" required class="form-input w-full">{{ old('verification_notes') }}</textarea>
                <label class="flex gap-3 text-sm"><input class="mt-1" type="checkbox" name="verified" value="1" required><span>I verified the property and landowner information outside the application.</span></label>
                <button class="btn-primary">Record property verification</button>
            </form>
        </section>
    @endcan

    @can('uploadDraft', $cts)
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="font-semibold text-emerald-950">Secretary/Admin — upload CTS draft</h2>
            <p class="mt-2 text-sm text-stone-600">Upload an externally prepared PDF (maximum 10 MB). A replacement draft is needed if the Buyer requested revisions.</p>
            <form method="POST" action="{{ route('cts.draft', $cts) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <label for="draft" class="block text-sm font-medium">CTS draft PDF</label>
                <input id="draft" type="file" name="document" accept="application/pdf,.pdf" required class="form-input w-full">
                <button class="btn-primary">Upload and send for Buyer review</button>
            </form>
        </section>
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
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">CTS processing is recorded as completed. Payment monitoring and final lot sale status will be handled in a subsequent phase.</div>
    @endif
</x-workspace>
