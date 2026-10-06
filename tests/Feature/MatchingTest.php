<?php

use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use App\Models\Fixture;
use App\Models\SportEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 15:00', 'UTC'));
});

/** Настоящая картинка: 300×200 PNG — эмблема, которую LogoStore впишет в 256×256. */
function pngLogo(): string
{
    $image = imagecreatetruecolor(300, 200);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 0, 90, 200));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/** Видимые события каталога: только матчи с показами. */
function visibleTitles(): array
{
    return app(EventCatalog::class)->events(new EventQuery)->pluck('title')->all();
}

it('builds matches from TheSportsDB, stores logos locally and attaches the guide', function (): void {
    Storage::fake('public');
    config(['sports.fixtures.sources.thesportsdb.sports' => ['hockey' => 'Ice Hockey'], 'sports.fixtures.days_ahead' => 0]);
    Http::fake([
        'www.thesportsdb.com/*' => Http::response(['events' => [[
            'idEvent' => '1', 'strTimestamp' => '2026-10-06T16:30:00', 'strLeague' => 'Russian KHL',
            'strHomeTeam' => 'Dynamo Moscow', 'strAwayTeam' => 'Shanghai Dragons', 'strStatus' => 'P2', 'intHomeScore' => '2', 'intAwayScore' => '1',
            'strHomeTeamBadge' => 'https://r2.thesportsdb.com/dynamo.png', 'strAwayTeamBadge' => 'https://r2.thesportsdb.com/dragons.png',
        ]]]),
        'r2.thesportsdb.com/*' => Http::response(pngLogo(), 200, ['Content-Type' => 'image/png']),
    ]);

    $this->artisan('fixtures:import')->assertSuccessful();
    $this->artisan('logos:sync')->assertSuccessful();
    fakeDispatcharr([['1', '20261006162000', '20261006184000', '⋗ КХЛ. Динамо (Москва) – Шанхайские Драконы']]);
    $this->artisan('guide:import')->assertSuccessful();

    $match = SportEvent::query()->where('sport', 'hockey')->sole();
    $event = app(EventCatalog::class)->find((string) $match->id);

    expect($event->title)->toBe('Динамо (Москва) — Шанхайские Драконы')
        ->and($event->competition->name)->toBe('КХЛ')
        ->and($event->score()?->home)->toBe(2)
        ->and($event->home->logo)->toBe('/storage/logos/teams/'.sha1('https://r2.thesportsdb.com/dynamo.png').'.webp')
        ->and(getimagesizefromstring(Storage::disk('public')->get('logos/teams/'.sha1('https://r2.thesportsdb.com/dynamo.png').'.webp'))[0])->toBe(256)
        ->and($event->broadcasts[0]->live)->toBeTrue();
});

it('merges the same match from two sources and keeps its id', function (): void {
    $khl = scheduleMatch('hockey', 'Металлург Мг', 'Адмирал', '2026-10-06 16:30', ['source' => 'khl', 'competition' => 'КХЛ']);
    $tsdb = scheduleMatch('hockey', 'Metallurg Magnitogorsk', 'Admiral Vladivostok', '2026-10-06 16:35', ['source' => 'thesportsdb']);
    buildMatches();
    $id = matchOf($khl)->id;

    buildMatches();

    expect(matchOf($tsdb)->id)->toBe($id)
        ->and(SportEvent::query()->count())->toBe(1)
        // Основной источник — КХЛ: его время и названия.
        ->and(matchOf($khl)->starts_at->format('H:i'))->toBe('16:30')
        ->and(matchOf($khl)->title)->toBe('Металлург Мг — Адмирал');
});

it('removes a future match that disappeared from every source', function (): void {
    $fixture = scheduleMatch('hockey', 'Трактор', 'Амур', '2026-10-07 14:00');
    buildMatches();
    $id = matchOf($fixture)->id;

    $fixture->delete();
    buildMatches();

    expect(SportEvent::query()->find($id))->toBeNull();
});

it('attaches a later showing of a past match as a replay', function (): void {
    scheduleMatch('hockey', 'СКА', 'Северсталь', '2026-10-05 16:30', ['competition' => 'КХЛ']);
    buildMatches();
    fakeDispatcharr([['1', '20261006090000', '20261006113000', 'КХЛ. СКА – Северсталь']]);

    $this->artisan('guide:import')->assertSuccessful();

    $match = SportEvent::query()->where('sport', 'hockey')->sole();
    expect($match->airings()->sole()->replay)->toBeTrue()
        ->and(app(EventCatalog::class)->find((string) $match->id)->isReplayOnly())->toBeTrue();
});

it('identifies a "Чемпионат КХЛ" slot by the only KHL game starting in it', function (): void {
    scheduleMatch('hockey', 'СКА', 'Северсталь', '2026-10-06 16:30', ['competition' => 'КХЛ']);
    buildMatches();
    fakeDispatcharr([['1', '20261006162000', '20261006184000', 'Чемпионат КХЛ']]);

    $this->artisan('guide:import')->assertSuccessful();

    expect(visibleTitles())->toContain('СКА — Северсталь');
});

it('does not guess a slot with two games starting in it', function (): void {
    scheduleMatch('hockey', 'Трактор', 'Амур', '2026-10-06 14:00', ['competition' => 'КХЛ']);
    scheduleMatch('hockey', 'Автомобилист', 'Авангард', '2026-10-06 14:00', ['competition' => 'КХЛ']);
    buildMatches();
    fakeDispatcharr([['1', '20261006132500', '20261006155000', 'Чемпионат КХЛ']]);

    $this->artisan('guide:import')->assertSuccessful();

    expect(visibleTitles())->toBe([]);
});

it('does not attach a broadcast to a different match', function (string $sport, string $home, string $away, string $guide): void {
    scheduleMatch($sport, $home, $away, '2026-10-06 16:30', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr([['1', '20261006162000', '20261006184000', $guide]]);

    $this->artisan('guide:import')->assertSuccessful();

    expect(visibleTitles())->toBe([]);
})->with([
    'one team similar by accident' => ['basketball', 'Pallacanestro Reggiana', 'BK Pardubice', 'Фенербахче – Дубай. Баскетбол. Евролига'],
    'СКА is not ЦСКА' => ['hockey', 'Динамо М', 'ЦСКА', 'КХЛ. Динамо (Москва) – СКА'],
    'senior team is not U21' => ['football', 'England U21', 'Spain U21', 'Футбол. Англия – Испания. Лига наций'],
    'different club, same generic word' => ['football', 'Real Cartagena', 'Malaga', 'Футбол. Испания. Реал Мадрид – Малага'],
]);

it('shows nothing for sports the schedule does not cover', function (): void {
    fakeDispatcharr([
        ['1', '20261006110000', '20261006123000', 'Теннис: WTA 1000 Пекин: Матч 1'],
        ['2', '20261006120000', '20261006140000', 'Формула 1. Гран-при Нидерландов. Гонка'],
    ]);

    $this->artisan('guide:import')->assertSuccessful();

    expect(SportEvent::query()->count())->toBe(0)->and(Fixture::query()->count())->toBe(0);
});
