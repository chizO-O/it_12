@props(['unreadNotificationCount' => 0])
@php
    $role = auth()->user()->role;
    $items = array_merge(
        [['label' => 'Dashboard', 'route' => $role->dashboardRoute()]],
        [['label' => 'Notifications', 'route' => 'notifications.index']],
        config('workspace.navigation.'.$role->value, []),
        [['label' => 'My account', 'route' => 'account']],
    );
@endphp
<nav class="space-y-1" aria-label="Workspace navigation">
    @foreach($items as $item)
        @php($active = $item['route'] && request()->routeIs($item['route'], str_replace('.index', '.*', $item['route'])))
        @if($item['route'])
            <a href="{{ route($item['route']) }}"
                class="workspace-link {{ $active ? 'bg-emerald-100 font-semibold text-emerald-950' : 'text-stone-600 hover:bg-stone-100' }}"
                @if($active) aria-current="page" @endif>
                {{ $item['label'] }}
                @if($item['route'] === 'notifications.index')
                    @if($unreadNotificationCount > 0)
                        <span class="ml-auto rounded-full bg-emerald-700 px-2 py-0.5 text-xs font-semibold text-white" aria-label="{{ $unreadNotificationCount }} unread notifications">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                    @endif
                @endif
            </a>
        @else
            <span class="workspace-link justify-between gap-3 text-stone-400" aria-disabled="true">
                <span>{{ $item['label'] }}</span>
                <span class="rounded bg-stone-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider">Soon</span>
            </span>
        @endif
    @endforeach
</nav>
