<?php

use App\Http\Controllers\Web\Catalog\AthleteController;
use App\Http\Controllers\Web\Catalog\CategoryController;
use App\Http\Controllers\Web\Catalog\CompetitionController;
use App\Http\Controllers\Web\Catalog\EventController as CatalogEventController;
use App\Http\Controllers\Web\Catalog\SportController as CatalogSportController;
use App\Http\Controllers\Web\Catalog\TeamController;
use App\Http\Controllers\Web\Catalog\WatchController as CatalogWatchController;
use App\Http\Controllers\Web\ChannelController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\ModeController;
use App\Http\Controllers\Web\SportController;
use App\Http\Controllers\Web\WatchController;
use App\Http\Middleware\DeferBroadcastPages;
use App\Http\Middleware\StandaloneLegacyPages;
use Illuminate\Support\Facades\Route;

/*
 * Веб-клиент — только после входа. JSON API для ТВ-клиентов — под /api
 * (routes/api.php).
 *
 * Спортивный каталог (docs/sports-catalog-reorganization-plan.md):
 * вид спорта → раздел (страна, регион, тур) → турнир → событие; команды,
 * сборные и спортсмены — свои страницы. Трансляции событий — на странице
 * события и странице просмотра; каналы отдельно не показываются.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    // Главная, расписание и поиск зависят от режима (SPORTS_MODE): каталог или прежнее приложение.
    Route::get('/', [ModeController::class, 'home'])->name('home');
    Route::get('/schedule', [ModeController::class, 'schedule'])->name('schedule');
    Route::get('/search', [ModeController::class, 'search'])->name('search');

    Route::get('/sport/{sport}', [CatalogSportController::class, 'show'])->name('catalog.sport');
    Route::get('/sport/{sport}/competitions', [CatalogSportController::class, 'competitions'])->name('catalog.sport.competitions');
    Route::get('/sport/{sport}/national-teams', [CatalogSportController::class, 'nationalTeams'])->name('catalog.sport.national-teams');
    Route::get('/sport/{sport}/players', [CatalogSportController::class, 'players'])->name('catalog.sport.players');
    Route::get('/sport/{sport}/{category}', [CategoryController::class, 'show'])->name('catalog.category');
    Route::get('/sport/{sport}/{category}/{competition}', [CompetitionController::class, 'show'])->name('catalog.competition');

    Route::get('/team/{team}/{slug?}', [TeamController::class, 'show'])->whereNumber('team')->name('catalog.team');
    Route::get('/player/{athlete}/{slug?}', [AthleteController::class, 'show'])->whereNumber('athlete')->name('catalog.player');
    Route::get('/event/{catalogEvent}', [CatalogEventController::class, 'show'])->whereNumber('catalogEvent')->name('catalog.event');
    Route::get('/event/{catalogEvent}/watch/{broadcast?}', [CatalogWatchController::class, 'show'])->whereNumber(['catalogEvent', 'broadcast'])->name('catalog.watch');
    Route::get('/event/{catalogEvent}/watch/{broadcast}/playlist.m3u', [CatalogWatchController::class, 'playlist'])->whereNumber(['catalogEvent', 'broadcast'])->name('catalog.playlist');
});

/*
 * Прежнее приложение «матчи с трансляциями» (SPORTS_MODE=iptv). В
 * самостоятельном режиме его страницы ведут в каталог; код и данные сохранены.
 * {event} — событие старого каталога, привязка в AppServiceProvider.
 */
Route::middleware(['auth', 'verified', StandaloneLegacyPages::class])->group(function (): void {
    Route::get('/sports/{sport}', [SportController::class, 'show'])->name('sports.show');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/events/{event}/watch/{broadcast?}', [WatchController::class, 'show'])
        ->middleware(DeferBroadcastPages::class)
        ->whereNumber('broadcast')
        ->name('events.watch');
    Route::get('/events/{event}/watch/{broadcast}/playlist.m3u', [WatchController::class, 'playlist'])
        ->middleware(DeferBroadcastPages::class)
        ->whereNumber('broadcast')
        ->name('events.playlist');
    Route::get('/channels', [ChannelController::class, 'index'])->middleware(DeferBroadcastPages::class)->name('channels.index');
    Route::get('/channels/{channel}', [ChannelController::class, 'show'])->middleware(DeferBroadcastPages::class)->name('channels.show');
});

require __DIR__.'/settings.php';
