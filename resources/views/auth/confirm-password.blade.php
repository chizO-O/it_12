<x-auth-card title="Confirm it's you" description="Enter your GroveMap password before connecting your Google account.">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <x-form-field name="password" label="Current password" type="password" autocomplete="current-password" autofocus />
        <button class="btn-primary w-full">Confirm password</button>
    </form>
</x-auth-card>
