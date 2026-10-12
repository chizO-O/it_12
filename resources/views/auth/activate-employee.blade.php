<x-auth-card title="Activate employee account" description="Use your assigned email and the one-time setup code shared privately by your Secretary or Unit Manager. The code expires after 60 minutes.">
    <form method="POST" action="{{ route('employee-activation.store') }}" class="space-y-5">
        @csrf
        <x-form-field name="email" label="Assigned email address" type="email" autocomplete="email" maxlength="255" required />
        <div>
            <label for="code" class="mb-2 block text-sm font-medium">One-time setup code</label>
            <input id="code" name="code" type="text" autocomplete="off" spellcheck="false" required maxlength="255" class="form-input w-full font-mono text-sm" />
            @error('code')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <x-form-field name="password" label="Create a password (12+ characters)" type="password" autocomplete="new-password" minlength="12" required />
        <x-form-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" minlength="12" required />
        <button class="btn-primary w-full">Activate account</button>
    </form>
    <p class="mt-5 text-sm text-stone-500">Your setup code is single-use. If it has expired, ask the person who created your account to issue another code.</p>
    <p class="mt-3 text-sm"><a href="{{ route('login') }}" class="text-link">Back to sign in</a></p>
</x-auth-card>
