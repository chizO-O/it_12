@if(config('services.google.client_id') && config('services.google.client_secret'))
    <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-widest text-stone-400"><span class="h-px flex-1 bg-stone-200"></span>or<span class="h-px flex-1 bg-stone-200"></span></div>
    <a href="{{ route('google.redirect') }}" class="btn-secondary w-full">Continue with Google</a>
@endif
