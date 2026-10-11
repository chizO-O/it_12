<x-workspace title="Manage Agents">
    <div class="mb-7">
        <p class="eyebrow">Unit Manager · Account management</p>
        <h1 class="mt-2 text-3xl font-semibold text-emerald-950">Manage Agents</h1>
        <p class="mt-2 text-sm text-stone-600">Invite your Agents. They set their own password through the secure email invitation.</p>
    </div>
    <x-feedback />
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Invite a new Agent</h2>
            <form action="{{ route('agents.store') }}" method="POST" class="mt-5 space-y-4">
                @csrf
                <x-form-field name="name" label="Agent name" maxlength="255" />
                <x-form-field name="email" label="Agent email" type="email" maxlength="255" />
                <button class="btn-primary">Create Agent and invite</button>
            </form>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Your Agents</h2>
            <div class="mt-4 space-y-3">
                @forelse($agents as $agent)
                    <article class="flex flex-wrap justify-between gap-3 rounded-xl border border-stone-100 p-4">
                        <div><p class="font-semibold">{{ $agent->name }}</p><p class="text-sm text-stone-500">{{ $agent->email }}</p>
                            <p class="text-xs text-stone-500">{{ $agent->password === null ? 'Invitation pending' : ($agent->hasVerifiedEmail() ? 'Verified' : 'Verification pending') }}</p></div>
                        @if($agent->password === null)
                        <form method="POST" action="{{ route('agents.invite', $agent) }}">@csrf<button class="btn-secondary">Resend invitation</button></form>
                        @endif
                    </article>
                @empty <p class="text-stone-500">No Agents assigned to you yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $agents->links() }}</div>
        </section>
    </div>
    @if($unmanagedAgents->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Existing Agents without a Unit Manager</h2>
            <p class="mt-1 text-sm text-stone-500">Use this only to link legacy Agent accounts created under the previous account workflow.</p>
            <div class="mt-4 space-y-3">
                @foreach($unmanagedAgents as $agent)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
                        <span>{{ $agent->name }} · {{ $agent->email }}</span>
                        <form method="POST" action="{{ route('agents.claim', $agent) }}">@csrf<button class="btn-secondary">Manage Agent</button></form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-workspace>
