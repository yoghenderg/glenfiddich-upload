@props(['item', 'hidden' => false, 'priority' => false])
<article @if($hidden) hidden @endif class="media-card" data-media-card="{{ $item['id'] }}">
    <button type="button" data-open-media="{{ $item['id'] }}" aria-label="View full {{ $item['type'] }} from {{ $item['date_display'] }} at {{ $item['time_display'] }}" class="media-card__image-wrap {{ $item['type'] === 'image' ? 'media-card__image-wrap--framed' : '' }}">@if($item['type'] === 'video')<video src="{{ $item['src'] }}#t=0.1" muted playsinline preload="metadata" aria-hidden="true"></video>@else
        <span class="media-card__photo-box"><img src="{{ $item['src'] }}" alt="" width="{{ $item['width'] ?: 1066 }}" height="{{ $item['height'] ?: 1600 }}" loading="{{ $priority ? 'eager' : 'lazy' }}" @if($priority) fetchpriority="high" @endif decoding="async"></span>
        <img class="media-card__template" src="{{ asset('media/Pocket Size_no line.png') }}" alt="" width="1800" height="1200" decoding="async">
        @endif
        @if($item['type'] === 'video')<span class="media-card__play"><x-icon name="video" :size="18"/></span>@endif</button>
    <span class="media-card__footer"><time class="media-card__timestamp" datetime="{{ $item['captured_at'] }}"><span class="media-card__date">{{ $item['date_display'] }}</span><span class="media-card__time">{{ $item['time_display'] }}</span></time></span>
    <div class="media-card__actions" aria-label="Media actions">
        <button type="button" data-card-action="share" title="Share QR code" aria-haspopup="dialog"><x-icon name="airdrop" :size="18"/><span>Share</span></button>
        <button type="button" data-card-action="download" title="Download"><x-icon name="download" :size="18"/><span>Download</span></button>
        <button type="button" data-card-action="print" title="Print 4R photo" @disabled($item['type'] !== 'image')><x-icon name="print" :size="18"/><span>Print</span></button>
    </div>
    <p class="media-card__hint" data-card-hint role="status" aria-live="polite"></p>
</article>
