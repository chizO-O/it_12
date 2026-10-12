<x-workspace title="Lot Agent Assignments">
    <div class="mb-7">
        <p class="eyebrow">Unit Manager · Assignment</p>
        <h1 class="mt-2 text-3xl font-semibold text-emerald-950">Assign Agents to Available lots</h1>
        <p class="mt-2 text-sm text-stone-600">One Agent per lot; one Agent may handle many lots. Assignment never reserves a lot. Existing buyer conversations cannot be transferred automatically.</p>
    </div>
    <x-feedback />
    @if($agents->isEmpty())
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">No active Agents under your supervision. <a class="text-link" href="{{ route('agents.index') }}">Invite or link an Agent first.</a></div>
    @endif
    <div class="space-y-4">
        @forelse($lots as $lot)
            <article class="rounded-2xl border border-stone-200 bg-white p-5">
                <div class="flex flex-wrap justify-between gap-4">
                    <div>
                        <p class="eyebrow">{{ $lot->property->location }} · {{ $lot->property->name }}</p>
                        <h2 class="mt-2 font-semibold text-lg">Lot {{ $lot->lot_no }} @if($lot->block_no) · Block {{ $lot->block_no }} @endif</h2>
                        <p class="text-sm text-stone-500">Current Agent: {{ $lot->assignedAgent?->name ?? 'Unassigned' }}</p>
                        @if($lot->awaiting_assignment_count > 0)
                            <p class="mt-2 text-sm font-semibold text-amber-800">{{ $lot->awaiting_assignment_count }} {{ (int) $lot->awaiting_assignment_count === 1 ? 'inquiry' : 'inquiries' }} awaiting assignment</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('assignments.update', $lot) }}" class="flex flex-wrap items-end gap-3">
                        @csrf @method('PATCH')
                        <div><label class="mb-1 block text-sm font-medium" for="agent-{{ $lot->id }}">Assigned Agent</label>
                        <select class="form-input min-w-48" id="agent-{{ $lot->id }}" name="agent_id">
                            <option value="">Unassigned</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($lot->assigned_agent_id === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </select></div>
                        <button class="btn-primary" type="submit">Save assignment</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed p-8 text-center text-stone-500">No Available lots to assign.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $lots->links() }}</div>
</x-workspace>
