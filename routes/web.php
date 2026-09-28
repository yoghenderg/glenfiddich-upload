<?php

use App\Http\Controllers\DemoMediaController;
use App\Http\Controllers\EventStaffLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/upload');
Route::get('/login', [EventStaffLoginController::class, 'create'])->name('login');
Route::post('/login', [EventStaffLoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::post('/logout', [EventStaffLoginController::class, 'destroy'])->middleware('event.staff')->name('logout');

Route::middleware('event.staff')->group(function (): void {
    Route::get('/upload', [DemoMediaController::class, 'upload'])->name('upload');
    Route::get('/gallery', [DemoMediaController::class, 'gallery'])->name('gallery');
    Route::get('/gallery/feed', [DemoMediaController::class, 'feed'])->name('gallery.feed');
});
Route::get('/media/{id}', [DemoMediaController::class, 'guest'])->name('media.guest');
