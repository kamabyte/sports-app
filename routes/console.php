<?php

use App\Console\Commands\ImportFixtures;
use App\Console\Commands\ImportGuide;
use App\Console\Commands\SyncLogos;
use Illuminate\Support\Facades\Schedule;

// Телегиды меняются в течение дня: показы пересобираются каждые полчаса (файлы телегидов — раз в sports.guides.refresh_hours).
Schedule::command(ImportGuide::class)
    ->everyThirtyMinutes()
    ->withoutOverlapping();

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
