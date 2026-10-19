    <div class="gallery-scroll" data-gallery-scroll tabindex="0" aria-label="Media gallery">
        @if($state === 'loading')
            <div class="media-grid" aria-label="Loading media">@for($i=0;$i<16;$i++)<div class="skeleton-card"><span></span><span></span></div>@endfor</div>
        @elseif($state === 'error' || $state === 'reconnecting')
            <div class="state-card"><span class="state-card__icon"><x-icon name="refresh" :size="28"/></span><h2>{{ $state === 'error' ? 'Gallery unavailable' : 'Reconnecting to gallery' }}</h2><p>{{ $state === 'error' ? 'Please try again.' : 'Trying to restore the connection.' }}</p><button class="button button--dark" id="retry-gallery" type="button">Try again</button></div>
        @elseif(count($items) === 0)
            <div class="state-card"><span class="state-card__icon"><x-icon name="image" :size="28"/></span><h2>No media yet</h2><p>Photos and videos will appear here.</p><a class="button button--dark" href="{{ route('upload') }}">Go to upload</a></div>
        @else
            <div class="media-grid" id="media-grid" data-count="{{ count($items) }}">@foreach($items as $item)<x-media-card :item="$item" :priority="$loop->index < 4"/>@endforeach</div>
        @endif
    </div>
    @if($state === 'ready')
    <nav class="pagination-bar" aria-label="Gallery pages">
        <span id="gallery-page-summary" role="status" aria-live="polite"><span id="media-count">{{ $pagination['total'] }}</span> {{ $pagination['total'] === 1 ? 'item' : 'items' }}</span>
        <div class="pagination-bar__controls" id="gallery-pagination">
            <button type="button" class="pagination-button" id="gallery-previous" aria-label="Previous page" aria-controls="media-grid" @disabled($pagination['page'] <= 1)>Previous</button>
            <span class="pagination-bar__current" id="gallery-page-number" role="status" aria-live="polite">{{ $pagination['page'] }} / {{ $pagination['pages'] }}</span>
            <button type="button" class="pagination-button" id="gallery-next" aria-label="Next page" aria-controls="media-grid" @disabled($pagination['page'] >= $pagination['pages'])>Next</button>
        </div>
    </nav>
    @endif
