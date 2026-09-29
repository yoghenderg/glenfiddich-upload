@extends('layouts.app')
@section('title', 'Upload')
@section('body-class', 'upload-page')
@section('navigation')
    <a class="nav-link is-active" href="{{ route('upload') }}" aria-current="page">Upload</a>
    <a class="nav-link" href="{{ route('gallery') }}">Gallery</a>
@endsection
@section('content')
<section class="upload-layout" aria-labelledby="upload-title">
    <div class="upload-panel card">
        <h1 id="upload-title">Share your photo</h1>
        <p class="upload-subtitle" id="upload-subtitle">JPG, PNG, WEBP · MP4, MOV · Max 200 MB</p>
        <form id="upload-form" data-upload-endpoint="{{ route('uploads.create') }}" novalidate>
            <input id="media-file" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime" class="visually-hidden" aria-describedby="upload-subtitle upload-feedback">
            <div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Choose a photo or video; you can also drop a file here">
                <div id="drop-empty" class="drop-zone__empty">
                    <span class="upload-glyph"><x-icon name="upload" :size="27"/></span>
                    <strong>Drag &amp; drop your file here</strong>
                    <span>or</span>
                    <span class="button button--soft button--small" aria-hidden="true">Browse file</span>
                </div>
                <div id="drop-preview" class="drop-zone__preview" hidden>
                    <div id="preview-media"></div>
                    <div class="drop-zone__preview-info"><strong id="preview-name"></strong><span id="preview-size"></span></div>
                </div>
            </div>
            <div class="upload-controls"><button type="button" id="replace-file" class="text-button" hidden>Choose a different file</button><button type="button" id="cancel-upload" class="text-button" hidden>Cancel upload</button></div>
            <button id="upload-button" class="button button--dark upload-submit" type="submit" disabled><x-icon name="upload" :size="18"/> <span id="upload-button-label">Upload media</span></button>
            <div class="progress" id="upload-progress" role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><div class="progress__bar" id="upload-progress-bar"></div></div>
            <p id="upload-feedback" class="form-feedback" role="status" aria-live="polite"></p>
        </form>
    </div>
</section>
@endsection
