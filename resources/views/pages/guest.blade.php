@extends('layouts.app')
@section('title', 'Your media')
@section('body-class', 'guest-page')
@if($item['type'] !== 'video')
    @push('head')
        <link rel="preload" as="image" href="{{ $item['src'] }}" fetchpriority="high">
    @endpush
@endif
@section('content')
<section class="guest-content" aria-label="Your event media">
    <div class="guest-card card">
        <div class="guest-card__media">@include('partials.media-display')</div>
        <a class="button button--dark guest-download" href="{{ $item['download'] }}" download="{{ $item['filename'] }}"><x-icon name="download" :size="18"/> Download {{ $item['type'] === 'video' ? 'video' : 'photo' }}</a>
    </div>
</section>
@endsection
