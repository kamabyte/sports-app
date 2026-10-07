<?php

use App\Http\Controllers\Web\ChannelController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ScheduleController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SportController;
use App\Http\Controllers\Web\WatchController;
use App\Http\Middleware\DeferBroadcastPages;
use Illuminate\Support\Facades\Route;

/*
 * Веб-клиент — только после входа. JSON API для ТВ-клиентов — под /api
 * (routes/api.php). {event} — событие каталога, привязка в AppServiceProvider.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/schedule', ScheduleController::class)->name('schedule');
    Route::get('/sports/{sport}', [SportController::class, 'show'])->name('sports.show');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/watch/{broadcast?}', [WatchController::class, 'show'])
        ->middleware(DeferBroadcastPages::class)
        ->whereNumber('broadcast')
        ->name('events.watch');
    Route::get('/events/{event}/watch/{broadcast}/playlist.m3u', [WatchController::class, 'playlist'])
        ->middleware(DeferBroadcastPages::class)
        ->whereNumber('broadcast')
        ->name('events.playlist');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/channels', [ChannelController::class, 'index'])->middleware(DeferBroadcastPages::class)->name('channels.index');
    Route::get('/channels/{channel}', [ChannelController::class, 'show'])->middleware(DeferBroadcastPages::class)->name('channels.show');
});

require __DIR__.'/settings.php';
