<x-layout title="Your property journey">
    <section class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-2 lg:items-center">
        <div>
            <p class="eyebrow">Real estate, with clarity</p>
            <h1 class="mt-6 max-w-xl text-5xl font-semibold leading-[1.1] tracking-tight text-emerald-950 sm:text-6xl">A place to begin.<br><span class="text-emerald-700">A future to build.</span></h1>
            <p class="mt-6 max-w-lg text-lg leading-relaxed text-stone-600">Welcome to GroveMap, SGRE Marketing's property management platform for buyers, agents, and office teams.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary">Go to my dashboard <span aria-hidden="true">&rarr;</span></a>
                @else
                    <a href="{{ route('register') }}" class="btn-primary">Create a buyer account <span aria-hidden="true">&rarr;</span></a>
                    <a href="{{ route('login') }}" class="btn-secondary">Sign in</a>
                @endauth
            </div>
            <div class="mt-12 border-t border-stone-200 pt-6"><p class="text-xs uppercase tracking-widest text-stone-500">Our property areas</p><div class="mt-4 flex flex-wrap gap-3"><span class="area-tag">Toril</span><span class="area-tag">Kidapawan</span><span class="area-tag">Kapalong</span></div></div>
        </div>
        <div class="relative overflow-hidden rounded-[2rem] bg-emerald-950 p-8 sm:p-12">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">Connected from the start</p>
            <div class="my-12 grid rotate-[-6deg] grid-cols-3 gap-3" aria-hidden="true">
                @foreach(['bg-emerald-800', 'bg-emerald-700', 'bg-lime-300', 'bg-emerald-600', 'bg-emerald-800', 'bg-emerald-700', 'bg-emerald-700', 'bg-emerald-600', 'bg-emerald-800'] as $color)
                    <div class="aspect-square rounded-xl border border-white/10 {{ $color }}"></div>
                @endforeach
            </div>
            <h2 class="text-2xl font-semibold text-white">Property. People. Progress.</h2>
            <p class="mt-3 text-sm leading-relaxed text-emerald-100">Your account brings you into the GroveMap community.</p>
        </div>
    </section>
</x-layout>
