<x-auth-card title="Set your password" description="Choose a password to activate your employee account or reset your existing password.">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form-field name="email" label="Email address" type="email" :value="$request->email" autocomplete="email" />
        <x-form-field name="password" label="New password" type="password" autocomplete="new-password" minlength="12" autofocus />
        <p class="text-xs text-stone-500">Use at least 12 characters.</p>
        <x-form-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" minlength="12" />
        <button class="btn-primary w-full">Save password</button>
    </form>
</x-auth-card>
