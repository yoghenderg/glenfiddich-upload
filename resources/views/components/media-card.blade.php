@props(['item'])
<button class="media-card" type="button" data-open-media="{{ $item['id'] }}" aria-label="Open {{ $item['type'] }} from {{ $item['date_display'] }} at {{ $item['time_display'] }}">
    <span class="media-card__image-wrap"><img src="{{ $item['poster'] }}" alt="" loading="lazy">@if($item['type'] === 'video')<span class="media-card__play"><x-icon name="video" :size="18"/></span>@endif</span>
    <span class="media-card__footer"><time class="media-card__timestamp" datetime="{{ $item['captured_at'] }}"><span class="media-card__date">{{ $item['date_display'] }}</span><span class="media-card__time">{{ $item['time_display'] }}</span></time></span>
</button>
