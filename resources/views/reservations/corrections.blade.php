@can('viewAssessmentFacts', $reservation)
    <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
        <h2 class="font-semibold text-emerald-950">Information Corrections</h2>
        <p class="mt-2 text-sm leading-6 text-stone-600">Request corrections to inaccurate facts. Existing information stays unchanged until an authorized Unit Manager approves a request; the original values and decision remain in the history.</p>
        @can('requestCorrection', $reservation)
            @if(auth()->user()->role === \App\Enums\UserRole::Buyer && $reservation->buyerForm && $reservation->status !== \App\Enums\ReservationStatus::Pending)
                <x-correction-form :reservation="$reservation" section="contact" :labels="\App\Models\BuyerInformationCorrection::CONTACT_LABELS" />
            @endif
            @if($reservation->buyerAssessment)<x-correction-form :reservation="$reservation" section="assessment" :labels="\App\Models\BuyerAssessment::FACT_LABELS" />@endif
        @endcan
        <x-form-errors bag="correctionReview" />
        <div class="mt-5 space-y-5">
            @forelse($corrections as $correction)
                @php($labels = $correction->section === 'contact' ? \App\Models\BuyerInformationCorrection::CONTACT_LABELS : \App\Models\BuyerAssessment::FACT_LABELS)
                <article class="rounded-xl border border-stone-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-semibold text-emerald-950">{{ $correction->section === 'contact' ? 'Contact information' : 'Assessment facts' }} correction</h3><span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold">{{ ucfirst($correction->status) }}</span></div>
                    <p class="mt-2 text-xs text-stone-500">Requested by {{ $correction->requester->name }} · {{ $correction->created_at->format('M j, Y, g:i A') }}</p>
                    <dl class="mt-4 space-y-4 break-words text-sm">
                        @foreach($correction->proposed_values as $field => $value)<div><dt class="font-medium text-stone-600">{{ $labels[$field] }}</dt><dd class="mt-2 grid gap-2 sm:grid-cols-2"><div class="rounded-lg bg-stone-50 p-3"><span class="mb-1 block text-xs font-medium text-stone-500">Original</span><span class="whitespace-pre-wrap"><x-information-value :field="$field" :value="$correction->original_values[$field]" /></span></div><div class="rounded-lg bg-emerald-50 p-3"><span class="mb-1 block text-xs font-medium text-emerald-800">Proposed</span><span class="whitespace-pre-wrap"><x-information-value :field="$field" :value="$value" /></span></div></dd></div>@endforeach
                    </dl>
                    <p class="mt-4 whitespace-pre-wrap break-words text-sm"><strong>Reason:</strong> {{ $correction->reason }}</p>
                    @if($correction->reviewed_at)<p class="mt-3 text-xs text-stone-500">Reviewed by {{ $correction->reviewer->name }} · {{ $correction->reviewed_at->format('M j, Y, g:i A') }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $correction->review_notes }}</p>@endif
                    @can('reviewCorrection', $reservation)
                        @if($correction->status === 'pending')
                            <form method="POST" action="{{ route('reservations.corrections.review', [$reservation, $correction]) }}" class="mt-4 space-y-3 border-t border-stone-100 pt-4">
                                @csrf @method('PATCH')
                                <label for="correction-review-{{ $correction->id }}" class="block text-sm font-medium">Decision notes</label>
                                <textarea id="correction-review-{{ $correction->id }}" name="review_notes" class="form-input" rows="2" maxlength="2000" required></textarea>
                                <div class="flex flex-wrap gap-3"><button name="decision" value="approve" class="btn-primary">Approve Correction</button><button name="decision" value="reject" class="btn-secondary">Reject Correction</button></div>
                            </form>
                        @endif
                    @endcan
                </article>
            @empty<p class="text-sm text-stone-500">No correction requests recorded.</p>@endforelse
        </div>
        @if($corrections instanceof \Illuminate\Pagination\LengthAwarePaginator)<div class="mt-5">{{ $corrections->links() }}</div>@endif
    </section>
@endcan
