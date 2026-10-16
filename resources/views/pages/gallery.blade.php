@extends('layouts.app')
@section('title', 'Gallery')
@section('body-class', 'gallery-page')
@section('navigation')
    <a class="nav-link" href="{{ route('upload') }}">Upload</a>
    <a class="nav-link is-active" href="{{ route('gallery') }}" aria-current="page">Gallery</a>
@endsection
@push('head')
    @if($state === 'ready' && count($items) > 0 && $items[0]['poster'])<link rel="preload" as="image" href="{{ asset($items[0]['poster']) }}" fetchpriority="high">@endif
@endpush
@section('content')
<section class="gallery-content" data-gallery data-feed-url="{{ route('gallery.feed', ['sort' => $sort]) }}" data-guest-base="{{ url('/media') }}" aria-labelledby="gallery-title">
    <div class="gallery-heading">
        <div class="gallery-heading__title">
            <div class="gallery-heading__copy">
                <p class="gallery-heading__eyebrow">RAY-BAN MOMENTS</p>
                <div class="gallery-heading__title-row">
                    <h1 id="gallery-title">Captured moments</h1>
                    <div id="live-status" class="live-status" data-status="{{ $state === 'ready' ? 'live' : $state }}" role="status" aria-live="polite"><span class="live-status__dot"></span><span id="live-label">{{ $state === 'ready' ? 'LIVE' : strtoupper($state) }}</span></div>
                </div>
            </div>
        </div>
        <details class="gallery-sort">
            <summary class="gallery-sort__trigger" aria-label="Filter gallery by date" title="Filter gallery by date">
                <x-icon name="filter" :size="19"/>
            </summary>
            <nav class="gallery-sort__menu" aria-label="Filter gallery by date">
                <a href="{{ route('gallery', ['sort' => 'none']) }}" @if($sort === 'none') aria-current="page" @endif>
                    <span>All dates</span>
                    @if($sort === 'none')<x-icon name="check" :size="16"/>@endif
                </a>
                <a href="{{ route('gallery', ['sort' => 'today']) }}" @if($sort === 'today') aria-current="page" @endif>
                    <span>Today</span>
                    @if($sort === 'today')<x-icon name="check" :size="16"/>@endif
                </a>
                <a href="{{ route('gallery', ['sort' => 'yesterday']) }}" @if($sort === 'yesterday') aria-current="page" @endif>
                    <span>Yesterday</span>
                    @if($sort === 'yesterday')<x-icon name="check" :size="16"/>@endif
                </a>
            </nav>
        </details>
    </div>
    <div class="gallery-scroll" data-gallery-scroll tabindex="0" aria-label="Media gallery">
        @if($state === 'loading')
            <div class="media-grid" aria-label="Loading media">@for($i=0;$i<16;$i++)<div class="skeleton-card"><span></span><span></span></div>@endfor</div>
        @elseif($state === 'error' || $state === 'reconnecting')
            <div class="state-card"><span class="state-card__icon"><x-icon name="refresh" :size="28"/></span><h2>{{ $state === 'error' ? 'Gallery unavailable' : 'Reconnecting to gallery' }}</h2><p>{{ $state === 'error' ? 'Please try again.' : 'Trying to restore the connection.' }}</p><button class="button button--dark" id="retry-gallery" type="button">Try again</button></div>
        @elseif(count($items) === 0)
            <div class="state-card"><span class="state-card__icon"><x-icon name="image" :size="28"/></span><h2>No media yet</h2><p>Photos and videos will appear here.</p><a class="button button--dark" href="{{ route('upload') }}">Go to upload</a></div>
        @else
            <div class="media-grid" id="media-grid" data-count="{{ count($items) }}">@foreach($items as $item)<x-media-card :item="$item" :hidden="$loop->index >= 16" :priority="$loop->index < 4"/>@endforeach</div>
        @endif
    </div>
    @if($state === 'ready' && count($items) > 0)
    <nav class="pagination-bar" aria-label="Gallery pages">
        <span id="gallery-page-summary" role="status" aria-live="polite"><span id="media-count">{{ count($items) }}</span> items · Page 1 of {{ (int) ceil(count($items) / 16) }}</span>
        <div class="pagination-bar__controls" id="gallery-pagination" hidden>
            <button type="button" class="pagination-button" id="gallery-previous" aria-label="Previous page" aria-controls="media-grid" disabled><x-icon name="arrow" :size="17"/></button>
            <span class="pagination-bar__current" id="gallery-page-number" aria-hidden="true">1 / {{ (int) ceil(count($items) / 16) }}</span>
            <button type="button" class="pagination-button" id="gallery-next" aria-label="Next page" aria-controls="media-grid"><x-icon name="arrow" :size="17"/></button>
        </div>
    </nav>
    @endif
</section>
<div class="viewer" id="media-viewer" hidden>
    <div class="viewer__backdrop" data-close-viewer></div>
    <section class="viewer__dialog" role="dialog" aria-modal="true" aria-labelledby="viewer-title">
        <button class="viewer__close icon-button" type="button" data-close-viewer aria-label="Close media viewer"><x-icon name="close" :size="18"/></button>
        <div class="viewer__media" id="viewer-media"></div>
        <aside class="viewer__sidebar">
            <img class="viewer__brand" src="{{ asset('media/partner-logo.png') }}" alt="Ray-Ban" width="2048" height="1180">
            <h2 id="viewer-title" class="viewer__timestamp"><span id="viewer-date"></span><time id="viewer-time"></time></h2>
            <div class="viewer__qr-area"><p class="viewer__qr-label">SCAN TO DOWNLOAD</p><div class="qr-frame"><canvas id="viewer-qr" width="160" height="160" aria-label="QR code for this moment"></canvas></div></div>
            <div class="viewer__actions" aria-label="Photo actions">
                <a id="viewer-download" class="button button--soft" download aria-label="Download photo" title="Download"><x-icon name="download" :size="21"/></a>
                <button type="button" id="viewer-share" class="button button--soft" aria-label="Share / AirDrop" title="Share / AirDrop"><x-icon name="airdrop" :size="23"/></button>
                <button type="button" id="viewer-print" class="button button--soft" aria-label="Print photo at 4 by 6 inches" title="Print 4R photo"><x-icon name="print" :size="21"/></button>
            </div>
            <p class="viewer__hint" id="viewer-hint" role="status" aria-live="polite"></p>
        </aside>
    </section>
</div>
@push('scripts')<script>window.demoMedia = @json($items);</script>@endpush
@endsection
