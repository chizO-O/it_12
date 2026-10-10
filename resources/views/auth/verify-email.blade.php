<x-auth-card title="Check your inbox" description="Verify your email address before accessing your GroveMap workspace.">
    <p class="text-sm leading-relaxed text-stone-600">We sent a verification link to <strong class="text-stone-800">{{ auth()->user()->email }}</strong>. Open the link to continue. If you don't see it, check your spam folder or request a new link.</p>
    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button class="btn-primary w-full">Resend verification email</button>
    </form>
    <a href="{{ route('dashboard') }}" class="btn-secondary mt-3 w-full">I've verified my email</a>
</x-auth-card>
