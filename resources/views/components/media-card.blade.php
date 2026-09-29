@props(['item', 'hidden' => false, 'priority' => false])
<button @if($hidden) hidden @endif class="media-card" type="button" data-open-media="{{ $item['id'] }}" aria-label="Open {{ $item['type'] }} from {{ $item['date_display'] }} at {{ $item['time_display'] }}">
    <span class="media-card__image-wrap">@if($item['type'] === 'video')<video src="{{ $item['src'] }}#t=0.1" muted playsinline preload="metadata" aria-hidden="true"></video>@else<img src="{{ $item['poster'] }}" alt="" width="{{ $item['width'] ?: 1066 }}" height="{{ $item['height'] ?: 1600 }}" loading="{{ $priority ? 'eager' : 'lazy' }}" @if($priority) fetchpriority="high" @endif decoding="async">@endif
        @if($item['type'] === 'video')<span class="media-card__play"><x-icon name="video" :size="18"/></span>@endif</span>
    <span class="media-card__footer"><time class="media-card__timestamp" datetime="{{ $item['captured_at'] }}"><span class="media-card__date">{{ $item['date_display'] }}</span><span class="media-card__time">{{ $item['time_display'] }}</span></time></span>
</button>
