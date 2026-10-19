@push('head')
    @if($activeView === 'gallery' && $state === 'ready' && count($items) > 0 && $items[0]['poster'])<link rel="preload" as="image" href="{{ asset($items[0]['poster']) }}" fetchpriority="high">@endif
@endpush

<section class="gallery-content" data-gallery data-gallery-state="{{ $state }}" data-feed-url="{{ route('gallery.feed', ['sort' => $sort]) }}" data-guest-base="{{ url('/media') }}" aria-labelledby="gallery-title">
    <div class="gallery-heading">
        <div class="gallery-heading__title">
            <div class="gallery-heading__copy">
                <p class="gallery-heading__eyebrow">RAY-BAN MOMENTS</p>
                <div class="gallery-heading__title-row">
                    <h1 id="gallery-title">Captured moments</h1>
                    <span class="gallery-loading" data-gallery-loading hidden role="status"><span class="mini-spinner" aria-hidden="true"></span><span class="visually-hidden">Updating gallery</span></span>
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
                    <span data-sort-check @if($sort !== 'none') hidden @endif><x-icon name="check" :size="16"/></span>
                </a>
                <a href="{{ route('gallery', ['sort' => 'today']) }}" @if($sort === 'today') aria-current="page" @endif>
                    <span>Today</span>
                    <span data-sort-check @if($sort !== 'today') hidden @endif><x-icon name="check" :size="16"/></span>
                </a>
                <a href="{{ route('gallery', ['sort' => 'yesterday']) }}" @if($sort === 'yesterday') aria-current="page" @endif>
                    <span>Yesterday</span>
                    <span data-sort-check @if($sort !== 'yesterday') hidden @endif><x-icon name="check" :size="16"/></span>
                </a>
            </nav>
        </details>
    </div>
    <p class="gallery-feedback" data-gallery-feedback role="status" aria-live="polite"></p>
    <div class="gallery-results" data-gallery-results>
        @include('partials.gallery-results')
    </div>
</section>
<div class="viewer" id="media-viewer" hidden>
    <div class="viewer__backdrop" data-close-viewer></div>
    <section class="viewer__dialog viewer__dialog--full" role="dialog" aria-modal="true" aria-label="Full media view">
        <button class="viewer__close icon-button" type="button" data-close-viewer aria-label="Close media viewer"><x-icon name="close" :size="18"/></button>
        <div class="viewer__media" id="viewer-media"></div>
        <p class="viewer__hint" id="viewer-hint" role="status" aria-live="polite"></p>
    </section>
</div>
<div class="viewer" id="share-modal" hidden>
    <div class="viewer__backdrop" data-close-share></div>
    <section class="share-dialog" role="dialog" aria-modal="true" aria-labelledby="share-title" aria-describedby="share-description">
        <button class="viewer__close icon-button" type="button" data-close-share data-share-close-button aria-label="Close share dialog"><x-icon name="close" :size="18"/></button>
        <h2 id="share-title">Scan to download</h2>
        <p id="share-description">Scan this QR code with your phone to open your moment.</p>
        <div class="share-dialog__qr" id="share-qr"></div>
        <p id="share-hint" role="status" aria-live="polite"></p>
        <a class="button button--dark" id="share-link" target="_blank" rel="noopener">Open download page</a>
    </section>
</div>
<div class="viewer" id="delete-modal" hidden>
    <div class="viewer__backdrop" data-close-delete></div>
    <section class="share-dialog delete-dialog" role="alertdialog" aria-modal="true" aria-labelledby="delete-title" aria-describedby="delete-description delete-filename">
        <h2 id="delete-title">Are you sure?</h2>
        <p id="delete-description">This will permanently delete this photo or video. This cannot be undone.</p>
        <p id="delete-filename"></p>
        <p id="delete-hint" role="status" aria-live="polite"></p>
        <div class="delete-dialog__actions">
            <button class="button button--soft" type="button" data-close-delete id="delete-cancel">Cancel</button>
            <button class="button button--dark" type="button" id="delete-confirm">Delete</button>
        </div>
    </section>
</div>
@push('scripts')<script>window.demoMedia = @json($items); window.galleryPagination = @json($pagination); window.galleryVersion = @json($version);</script>@endpush
