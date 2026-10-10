<x-auth-card title="Forgot your password?" description="Enter your account email and we'll send you a password reset link.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-form-field name="email" label="Email address" type="email" autocomplete="email" autofocus />
        <button class="btn-primary w-full">Send reset link</button>
    </form>
    <p class="mt-7 text-center text-sm"><a href="{{ route('login') }}" class="text-link">Back to sign in</a></p>
</x-auth-card>
