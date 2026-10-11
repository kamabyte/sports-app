<?php

use App\Console\Commands\DiscoverCatalog;
use App\Console\Commands\ImportCatalog;
use App\Console\Commands\ImportFixtures;
use App\Console\Commands\ImportGuide;
use App\Console\Commands\SyncLogos;
use App\Sports\CatalogDatabase;
use Illuminate\Support\Facades\Schedule;

// Телегиды меняются в течение дня: показы пересобираются каждые полчаса (файлы
// телегидов — раз в sports.guides.refresh_hours). В каталоге — показы его событий.
Schedule::command(ImportGuide::class)
    ->everyThirtyMinutes()
    ->withoutOverlapping();

if (CatalogDatabase::standalone()) {
    // Расписание каталога: только Flashscore и только если владелец его включил
    // (FLASHSCORE_ENABLED). Прежние источники расписания не запускаются.
    if (config('sports.catalog.flashscore.enabled')) {
        // Дневные списки и страницы турниров в пределах бюджета (pages_per_run).
        Schedule::command(ImportCatalog::class)
            ->hourlyAt(5)
            ->withoutOverlapping()
            ->runInBackground();

        // Реестр турниров из карты сайта — раз в неделю, два запроса.
        Schedule::command(DiscoverCatalog::class)
            ->weeklyOn(1, '04:30')
            ->withoutOverlapping();
    }

    return;
}

// Расписание матчей: бесплатный TheSportsDB медленный (пауза между запросами) — раз в час.
Schedule::command(ImportFixtures::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Эмблемы команд и турниров — к нам, после загрузки расписания.
Schedule::command(SyncLogos::class)
    ->hourlyAt(20)
    ->withoutOverlapping()
    ->runInBackground();
