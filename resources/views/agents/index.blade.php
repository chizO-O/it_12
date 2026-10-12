<x-workspace title="Manage Agents">
    <div class="mb-7">
        <p class="eyebrow">Unit Manager · Account management</p>
        <h1 class="mt-2 text-3xl font-semibold text-emerald-950">Manage Agents</h1>
        <p class="mt-2 text-sm text-stone-600">Create Agent accounts and share a private one-time setup code. No email verification is needed.</p>
    </div>
    <x-feedback />
    @if(session()->has('activation_code'))
    <section class="my-5 rounded-xl border border-amber-300 bg-amber-50 p-5" aria-label="Agent setup code">
        <h2 class="font-semibold text-amber-950">One-time Agent activation code</h2>
        <p class="mt-2 text-sm text-amber-900">Share privately with <strong>{{ session('activation_email') }}</strong>. Copy now: this code expires in 60 minutes and is shown only once.</p>
        <p class="mt-3 break-all rounded-lg bg-white p-3 font-mono text-sm select-all">{{ session('activation_code') }}</p>
        <p class="mt-3 text-sm text-amber-900">Agent opens <a class="underline" href="{{ route('employee-activation.create') }}">{{ route('employee-activation.create') }}</a> and enters their email, code, and password.</p>
    </section>
    @endif
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Create an Agent account</h2>
            <form action="{{ route('agents.store') }}" method="POST" class="mt-5 space-y-4">
                @csrf
                <x-form-field name="name" label="Agent name" maxlength="255" />
                <x-form-field name="email" label="Agent email" type="email" maxlength="255" />
                <button class="btn-primary">Create Agent and generate code</button>
            </form>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Your Agents</h2>
            <div class="mt-4 space-y-3">
                @forelse($agents as $agent)
                    <article class="flex flex-wrap justify-between gap-3 rounded-xl border border-stone-100 p-4">
                        <div><p class="font-semibold">{{ $agent->name }}</p><p class="text-sm text-stone-500">{{ $agent->email }}</p>
                            <p class="text-xs text-stone-500">{{ $agent->password === null ? 'Setup pending' : 'Active' }}</p></div>
                        @if($agent->password === null)
                        <form method="POST" action="{{ route('agents.invite', $agent) }}">@csrf<button class="btn-secondary">Generate new code</button></form>
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
