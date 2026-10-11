@props(['title'])
@php($role = auth()->user()->role)
@php($unreadNotificationCount = auth()->user()->unreadNotifications()->count())
<x-layout :title="$title" :workspace="true">
    <div class="mx-auto max-w-7xl px-5 py-7 sm:px-8 lg:flex lg:items-start lg:gap-8">
        <aside class="sticky top-7 hidden w-56 shrink-0 lg:block" aria-label="{{ $role->workspaceLabel() }} workspace">
            <div class="mb-6 rounded-2xl border border-stone-200 bg-white p-5">
                <p class="eyebrow">{{ $role->workspaceLabel() }} workspace</p>
                <p class="mt-3 truncate font-semibold text-emerald-950">{{ auth()->user()->name }}</p>
                <p class="mt-1 text-xs text-stone-500">{{ $role->label() }}</p>
            </div>
            <x-workspace-navigation :unread-notification-count="$unreadNotificationCount" />
        </aside>
        <div class="min-w-0 flex-1">
            <details class="mb-6 rounded-2xl border border-stone-200 bg-white lg:hidden">
                <summary class="cursor-pointer p-4 text-sm font-semibold text-emerald-950">{{ $role->workspaceLabel() }} workspace menu</summary>
                <div class="border-t border-stone-100 p-3"><x-workspace-navigation :unread-notification-count="$unreadNotificationCount" /></div>
            </details>
            {{ $slot }}
        </div>
    </div>
    <nav class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-around gap-2 border-t border-stone-200 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur lg:hidden" aria-label="Mobile navigation">
        <a href="{{ route($role->dashboardRoute()) }}" class="mobile-link" @if(request()->routeIs('*dashboard')) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-7h6v7"/></svg>
            Dashboard
        </a>
        @if($role === \App\Enums\UserRole::Admin)
            <a href="{{ route('employees.index') }}" class="mobile-link" @if(request()->routeIs('employees.*')) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 4a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5.65"/></svg>
                Employees
            </a>
        @endif
        @if(in_array($role, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Agent, \App\Enums\UserRole::Admin, \App\Enums\UserRole::UnitManager], true))
        <a href="{{ route('inventory.index') }}" class="mobile-link" @if(request()->routeIs('inventory.*')) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 3h18v18H3zM3 9h18M9 9v12M15 9v12"/></svg>
            Lots
        </a>
        @elseif($role === \App\Enums\UserRole::FieldPlanner)
        <a href="{{ route('properties.index') }}" class="mobile-link" @if(request()->routeIs('properties.*')) aria-current="page" @endif>Properties</a>
        @elseif($role === \App\Enums\UserRole::Cashier)
        <a href="{{ route('sales.index') }}" class="mobile-link" @if(request()->routeIs('sales.*')) aria-current="page" @endif>Payments</a>
        @endif
        <a href="{{ route('account') }}" class="mobile-link" @if(request()->routeIs('account')) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/></svg>
            My account
        </a>
    </nav>
</x-layout>
