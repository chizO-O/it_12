@props(['title', 'description'])
<x-layout :title="$title">
    <section class="mx-auto grid max-w-6xl gap-10 px-5 py-12 sm:px-8 sm:py-20 lg:grid-cols-2 lg:items-center">
        <div class="hidden lg:block">
            <p class="eyebrow">Welcome to GroveMap</p>
            <h1 class="mt-5 max-w-md text-5xl font-semibold leading-tight tracking-tight text-emerald-950">A clearer path to your next property.</h1>
            <p class="mt-6 max-w-md text-lg leading-relaxed text-stone-600">One place for SGRE buyers and employees to connect and follow every step of the property journey.</p>
            <div class="mt-10 flex flex-wrap gap-3 text-sm text-emerald-900"><span class="area-tag">Toril</span><span class="area-tag">Kidapawan</span><span class="area-tag">Kapalong</span></div>
        </div>
        <div class="w-full max-w-lg justify-self-center rounded-3xl border border-stone-200 bg-white p-6 shadow-sm sm:p-10">
            <p class="eyebrow">Your GroveMap account</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight text-emerald-950">{{ $title }}</h2>
            <p class="mt-3 text-sm leading-relaxed text-stone-600">{{ $description }}</p>
            <div class="mt-7"><x-feedback />{{ $slot }}</div>
        </div>
    </section>
</x-layout>
