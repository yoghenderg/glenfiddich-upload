@props(['name', 'size' => 20])
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('upload') <path d="M12 16V4m0 0L7 9m5-5 5 5"/><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/> @break
    @case('image') <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/> @break
    @case('video') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/> @break
    @case('download') <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v3h16v-3"/> @break
    @case('share') <path d="M12 16V3m0 0L7 8m5-5 5 5"/><path d="M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/> @break
    @case('airdrop') <path d="M5.64 17.36a9 9 0 1 1 12.72 0M7.76 15.24a6 6 0 1 1 8.48 0M9.95 13.05a2.9 2.9 0 1 1 4.1 0"/><path d="m9 16 3 5.5 3-5.5z" fill="currentColor" stroke="none"/> @break
    @case('print') <path d="M7 8V3h10v5M7 17H4V9h16v8h-3"/><path d="M7 14h10v7H7z"/><path d="M17 11h.01"/> @break
    @case('close') <path d="M5 5 19 19M19 5 5 19"/> @break
    @case('arrow') <path d="M4 12h16m0 0-6-6m6 6-6 6"/> @break
    @case('check') <path d="m4 12 5 5L20 6"/> @break
    @case('refresh') <path d="M20 11a8 8 0 1 0-2 6M20 4v7h-7"/> @break
@endswitch
</svg>
