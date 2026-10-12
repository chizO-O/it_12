<x-auth-card title="Welcome back" description="Sign in to continue your property journey.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-form-field name="email" label="Email address" type="email" autocomplete="username" autofocus />
        <x-form-field name="password" label="Password" type="password" autocomplete="current-password" />
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2"><input name="remember" type="checkbox" value="1" @checked(old('remember')) class="size-4 accent-emerald-800"> Remember me</label>
            <a href="{{ route('password.request') }}" class="text-link">Forgot password?</a>
        </div>
        <button class="btn-primary w-full">Sign in</button>
    </form>
    <x-google-button />
    <p class="mt-4 text-center text-sm text-stone-600">New employee? <a href="{{ route('employee-activation.create') }}" class="text-link">Activate your account with a setup code</a></p>
    <p class="mt-7 text-center text-sm text-stone-600">New to GroveMap? <a href="{{ route('register') }}" class="text-link">Create a buyer account</a></p>
</x-auth-card>
