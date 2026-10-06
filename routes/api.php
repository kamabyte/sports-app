<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SportController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;

/*
 * JSON API для ТВ-клиентов (Android TV, tvOS). Префикс /api задан в
 * bootstrap/app.php; имена маршрутов — с «api.», чтобы не путать их
 * со страницами веб-клиента (routes/web.php).
 */
Route::name('api.')->group(function (): void {
    // Вход устройства: email и пароль → токен Sanctum (Authorization: Bearer).
    Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::get('/home', HomeController::class)->name('home');
        Route::get('/sports', [SportController::class, 'index'])->name('sports.index');
        Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
        Route::get('/channels/{channel}', [ChannelController::class, 'show'])->name('channels.show');
        Route::get('/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/broadcasts/{broadcast}/stream', [StreamController::class, 'show'])
            ->whereNumber('broadcast')
            ->name('events.stream');
    });
});
