@extends('layouts.app')
@section('title', 'Your media')
@section('body-class', 'guest-page')
@if($item['type'] !== 'video')
    @push('head')
        <link rel="preload" as="image" href="{{ $item['src'] }}" fetchpriority="high">
    @endpush
@endif
@section('content')
<section class="guest-content" aria-label="Your event media" @if($item['type'] === 'image') data-framed-guest data-src="{{ $item['src'] }}" data-filename="{{ $item['filename'] }}" @endif>
    <div class="guest-card card">
        <div class="guest-card__media">@include('partials.media-display')</div>
        <a class="button button--dark guest-download" @if($item['type'] === 'video') href="{{ $item['download'] }}" @else aria-disabled="true" @endif download="{{ $item['filename'] }}"><x-icon name="download" :size="18"/> Download {{ $item['type'] === 'video' ? 'video' : 'photo' }}</a>
        @if($item['type'] === 'image')<p data-guest-hint role="status" aria-live="polite">Preparing your photo...</p>@endif
    </div>
</section>
@endsection
