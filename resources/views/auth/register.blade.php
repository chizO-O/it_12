<x-auth-card title="Find your place" description="Create your buyer account and choose a password. No email verification is required.">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <x-form-field name="name" label="Full name" autocomplete="name" maxlength="255" autofocus />
        <x-form-field name="email" label="Email address" type="email" autocomplete="email" maxlength="255" />
        <x-form-field name="password" label="Password" type="password" autocomplete="new-password" minlength="12" />
        <p class="text-xs text-stone-500">Use at least 12 characters.</p>
        <x-form-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" minlength="12" />
        <button class="btn-primary w-full">Create buyer account</button>
    </form>
    <x-google-button />
    <p class="mt-7 text-center text-sm text-stone-600">Already have an account? <a href="{{ route('login') }}" class="text-link">Sign in</a></p>
</x-auth-card>
