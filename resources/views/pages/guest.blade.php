@extends('layouts.app')
@section('title', 'Your media')
@section('body-class', 'guest-page')
@section('content')
<section class="guest-content" aria-label="Your event media">
    <div class="guest-card">
        <div class="guest-card__media">@include('partials.media-display')</div>
        <a class="button button--dark guest-download" href="{{ $item['download'] }}" download="{{ $item['filename'] }}"><x-icon name="download" :size="18"/> Download {{ $item['type'] === 'video' ? 'video' : 'photo' }}</a>
    </div>
</section>
@endsection
