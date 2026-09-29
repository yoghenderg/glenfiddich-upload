@if($item['type'] === 'video')
    <video class="media-display" controls playsinline preload="metadata" poster="{{ $item['poster'] }}" aria-label="{{ $item['alt'] }}"><source src="{{ $item['src'] }}" type="{{ $item['mime_type'] }}">Your browser does not support video playback.</video>
@else
    <img class="media-display" src="{{ $item['src'] }}" alt="{{ $item['alt'] }}" width="{{ $item['width'] ?: 1066 }}" height="{{ $item['height'] ?: 1600 }}" loading="eager" fetchpriority="high" decoding="async">
@endif
