<x-workspace :title="$workspace.' dashboard'">
    <div>
        <x-feedback />
        <div class="rounded-3xl bg-emerald-950 p-7 text-white sm:p-10">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300">{{ $workspace }} workspace · {{ auth()->user()->role->label() }}</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Welcome, {{ auth()->user()->name }}.</h1>
            <p class="mt-4 max-w-2xl leading-relaxed text-emerald-100">Your GroveMap account is ready. This is your home for the property journey.</p>
        </div>
        <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-6">
            <div><h2 class="font-semibold text-emerald-950">My account</h2><p class="mt-1 text-sm text-stone-500">{{ auth()->user()->email }} · Email verified</p></div>
            <a href="{{ route('account') }}" class="btn-secondary">View account</a>
        </div>
        <div class="mb-5 mt-10">
            <h2 class="text-xl font-semibold text-emerald-950">Workspace tools</h2>
            <p class="mt-2 text-sm text-stone-500">Tools marked coming soon will be available as GroveMap grows.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($modules as $module)
                <article class="flex flex-col rounded-2xl border border-stone-200 bg-white p-6">
                    <span class="self-start rounded-full px-3 py-1 text-xs font-medium {{ $module['route'] ? 'bg-emerald-50 text-emerald-800' : 'bg-stone-100 text-stone-500' }}">
                        {{ $module['route'] ? 'Available' : 'Coming soon' }}
                    </span>
                    <h3 class="mt-5 font-semibold text-emerald-950">{{ $module['label'] }}</h3>
                    <p class="mt-3 flex-1 text-sm leading-relaxed text-stone-500">{{ $module['description'] }}</p>
                    @if($module['route'])
                        <a href="{{ route($module['route']) }}" class="text-link mt-5 text-sm">{{ $module['label'] === 'Employees' ? 'Manage employees' : 'Open tool' }} <span aria-hidden="true">&rarr;</span></a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</x-workspace>
