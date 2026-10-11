<?php

use App\Http\Controllers\Catalog\CompetitionController;
use App\Http\Controllers\Catalog\EventController as CatalogEventController;
use App\Http\Controllers\Catalog\HomeController as CatalogHomeController;
use App\Http\Controllers\Catalog\PlayerController;
use App\Http\Controllers\Catalog\ScheduleController;
use App\Http\Controllers\Catalog\SearchController;
use App\Http\Controllers\Catalog\SportController as CatalogSportController;
use App\Http\Controllers\Catalog\TeamController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\DeviceCodeController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\SportController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\TokenController;
use App\Http\Middleware\LegacyApi;
use Illuminate\Support\Facades\Route;

/*
 * JSON API для ТВ-клиентов (Android TV, tvOS), docs/api.md. Префикс /api
 * задан в bootstrap/app.php; имена маршрутов — с «api.», чтобы не путать
 * их со страницами веб-клиента (routes/web.php).
 *
 * /api/v1 — спортивный каталог: те же данные, что у веб-клиента
 * (App\Sports\Catalog\Pages), сущности по id и ключам вместо адресов страниц.
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Вход устройства: email и пароль → токен Sanctum (Authorization: Bearer).
    Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('tokens.store');
    // Вход по коду: код на экране ТВ, подтверждение на сайте (/device), опрос за токеном.
    Route::post('/device/codes', [DeviceCodeController::class, 'store'])->middleware('throttle:10,1')->name('device.codes.store');
    Route::post('/device/token', [DeviceCodeController::class, 'token'])->middleware('throttle:60,1')->name('device.token');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');
        Route::get('/me', MeController::class)->name('me');

        Route::get('/home', CatalogHomeController::class)->name('home');
        Route::get('/schedule', ScheduleController::class)->name('schedule');
        Route::get('/search', SearchController::class)->name('search');

        Route::get('/sports', [CatalogSportController::class, 'index'])->name('sports.index');
        Route::get('/sports/{sport}', [CatalogSportController::class, 'show'])->name('sports.show');
        Route::get('/sports/{sport}/competitions', [CatalogSportController::class, 'competitions'])->name('sports.competitions');
        Route::get('/sports/{sport}/categories/{category}', [CatalogSportController::class, 'category'])->name('sports.category');
        Route::get('/sports/{sport}/national-teams', [CatalogSportController::class, 'nationalTeams'])->name('sports.national-teams');
        Route::get('/sports/{sport}/players', [CatalogSportController::class, 'players'])->name('sports.players');

        Route::get('/competitions/{competition}', [CompetitionController::class, 'show'])->whereNumber('competition')->name('competitions.show');
        Route::get('/teams/{team}', [TeamController::class, 'show'])->whereNumber('team')->name('teams.show');
        Route::get('/players/{athlete}', [PlayerController::class, 'show'])->whereNumber('athlete')->name('players.show');
        Route::get('/events/{catalogEvent}', [CatalogEventController::class, 'show'])->whereNumber('catalogEvent')->name('events.show');
        Route::get('/events/{catalogEvent}/broadcasts/{broadcast}/stream', [CatalogEventController::class, 'stream'])
            ->whereNumber(['catalogEvent', 'broadcast'])
            ->name('events.stream');
    });
});

/*
 * Прежний API «матчей с трансляциями» (SPORTS_MODE=iptv). В самостоятельном
 * режиме данные отвечают 410 — LegacyApi; токены общие с /api/v1.
 */
Route::name('api.')->group(function (): void {
    Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::middleware(LegacyApi::class)->group(function (): void {
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
});
