<x-workspace title="My account">
    <div class="max-w-3xl">
        <p class="eyebrow">Your profile</p><h1 class="mt-3 text-3xl font-semibold text-emerald-950">My account</h1>
        <div class="mt-8"><x-feedback /></div>
        <section class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
            <h2 class="text-lg font-semibold text-emerald-950">Account details</h2>
            <form method="POST" action="{{ route('account.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PATCH')
                <x-form-field name="name" label="Full name" :value="auth()->user()->name" autocomplete="name" maxlength="255" />
                <button class="btn-primary">Save profile</button>
            </form>
            <dl class="mt-7 grid gap-6 border-t border-stone-100 pt-6 sm:grid-cols-2">
                <div><dt class="text-xs uppercase tracking-wider text-stone-500">Email address</dt><dd class="mt-2 break-all font-medium">{{ auth()->user()->email }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-stone-500">Account role</dt><dd class="mt-2 font-medium">{{ auth()->user()->role->label() }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-stone-500">Email status</dt><dd class="mt-2 font-medium text-emerald-700">Verified</dd></div>
            </dl>
        </section>
        <section class="mt-5 rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
            <h2 class="text-lg font-semibold text-emerald-950">Google sign-in</h2>
            @if(auth()->user()->google_id)
                <p class="mt-3 text-sm text-stone-600">Your Google account is connected. You can use it to sign in.</p>
            @elseif(config('services.google.client_id') && config('services.google.client_secret'))
                <p class="mt-3 text-sm leading-relaxed text-stone-600">Connect the Google account with the same email address. You'll first confirm your GroveMap password.</p>
                <a href="{{ route('google.link') }}" class="btn-secondary mt-5">Connect Google account</a>
            @else
                <p class="mt-3 text-sm text-stone-500">Google sign-in is not available yet.</p>
            @endif
        </section>
    </div>
</x-workspace>
