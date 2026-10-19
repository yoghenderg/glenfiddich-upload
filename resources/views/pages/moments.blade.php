@extends('layouts.app')
@section('title', $activeView === 'gallery' ? 'Gallery' : 'Upload')
@section('body-class', 'workspace-page '.$activeView.'-page')
@section('navigation')
    <a class="nav-link {{ $activeView === 'upload' ? 'is-active' : '' }}" href="{{ route('upload') }}" data-workspace-link="upload" @if($activeView === 'upload') aria-current="page" @endif>Upload</a>
    <a class="nav-link {{ $activeView === 'gallery' ? 'is-active' : '' }}" href="{{ route('gallery', ['sort' => $sort]) }}" data-workspace-link="gallery" @if($activeView === 'gallery') aria-current="page" @endif>Gallery</a>
@endsection
@section('content')
    <div class="workspace-panel" data-workspace-panel="upload" @if($activeView !== 'upload') hidden @endif>
        @include('pages.upload')
    </div>
    <div class="workspace-panel" data-workspace-panel="gallery" @if($activeView !== 'gallery') hidden @endif>
        @include('pages.gallery')
    </div>
@endsection
