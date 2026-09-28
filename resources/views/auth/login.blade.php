@extends('layouts.app')
@section('title', 'Staff login')
@section('body-class', 'login-page')
@section('content')
<section class="login-layout" aria-labelledby="login-title">
    <form class="login-card card" action="{{ route('login.store') }}" method="post">
        @csrf
        <p class="login-card__eyebrow">EVENT STAFF</p>
        <h1 id="login-title">Sign in</h1>
        <p class="login-card__intro">Access the upload station and media gallery.</p>
        <div class="login-field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')<p class="login-field__error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="login-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="button button--dark login-submit" type="submit">Sign in</button>
    </form>
</section>
@endsection
