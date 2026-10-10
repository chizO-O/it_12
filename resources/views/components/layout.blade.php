@props(['title' => 'GroveMap', 'workspace' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#163b2d">
    <title>{{ $title }} · GroveMap</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased {{ $workspace ? 'pb-20 lg:pb-0' : '' }}">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Skip to content</a>
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="GroveMap home">
                <span class="grid size-10 grid-cols-2 gap-1 rounded-xl bg-emerald-950 p-2.5" aria-hidden="true">
                    <span class="rounded-sm bg-lime-300"></span><span class="rounded-sm bg-emerald-500"></span>
                    <span class="rounded-sm bg-emerald-500"></span><span class="rounded-sm bg-emerald-700"></span>
                </span>
                <span><span class="block text-xl font-bold tracking-tight text-emerald-950">GroveMap</span><span class="block text-[10px] font-semibold uppercase tracking-[0.2em] text-stone-500">SGRE Marketing</span></span>
            </a>
            <nav class="flex flex-wrap items-center gap-4 text-sm font-medium" aria-label="Main navigation">
                @auth
                    @if(auth()->user()->hasVerifiedEmail() && ! $workspace)
                        <a href="{{ route('dashboard') }}" class="nav-link" @if(request()->routeIs('*dashboard')) aria-current="page" @endif>Dashboard</a>
                        @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                            <a href="{{ route('employees.index') }}" class="nav-link" @if(request()->routeIs('employees.*')) aria-current="page" @endif>Employees</a>
                        @endif
                        <a href="{{ route('account') }}" class="nav-link" @if(request()->routeIs('account')) aria-current="page" @endif>My account</a>
                    @endif
                    @if($workspace)
                        <span class="hidden max-w-48 truncate text-stone-500 sm:inline">{{ auth()->user()->name }}</span>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg border border-stone-200 px-3 py-2 hover:bg-stone-50">Sign out</button></form>
                @else
                    <a href="{{ route('login') }}" class="nav-link">Sign in</a>
                    <a href="{{ route('register') }}" class="btn-primary">Create account</a>
                @endauth
            </nav>
        </div>
    </header>
    <main id="main-content">{{ $slot }}</main>
    <footer class="mx-auto max-w-7xl px-5 py-8 text-xs text-stone-500 sm:px-8">
        <div class="flex flex-wrap justify-between gap-3 border-t border-stone-200 pt-6"><span>&copy; {{ now()->year }} GroveMap · SGRE Marketing</span><span>Property. People. Progress.</span></div>
    </footer>
</body>
</html>
