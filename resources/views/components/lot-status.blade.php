@props(['status'])
<span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $status->badgeClass() }}">{{ $status->label() }}</span>
