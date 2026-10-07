<?php

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Fixtures\EspnSource;
use App\Guide\Fixtures\EuroleagueSource;
use App\Guide\Fixtures\FixtureImporter;
use App\Guide\Fixtures\FixtureSource;
use App\Guide\Fixtures\IbuSource;
use App\Guide\Fixtures\KhlSource;
use App\Guide\Fixtures\LiveScoreSource;
use App\Guide\Matches\MatchBuilder;
use App\Guide\Teams\TeamRegistry;
use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

// Ответы — урезанные копии настоящих (tests/Fixtures/sources).
function sourceSample(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/sources/{$name}.json")), true);
}

function importer(array $sources): FixtureImporter
{
    return new FixtureImporter($sources, new MatchBuilder, new TeamRegistry, new CompetitionRegistry);
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
    $this->day = CarbonImmutable::parse('2026-10-06', 'UTC');
});

it('reads ESPN scoreboards', function (): void {
    Http::fake(['site.api.espn.com/*' => Http::response(sourceSample('espn-nhl'))]);

    $fixtures = (new EspnSource(['hockey/nhl' => 'hockey']))->fixtures($this->day);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/hockey/nhl/scoreboard') && $request['dates'] === '20261006');
    expect($fixtures)->toHaveCount(2)
        ->and($fixtures[0]->home)->toBe('Montreal Canadiens')
        ->and($fixtures[0]->away)->toBe('Carolina Hurricanes')
        ->and($fixtures[0]->startsAt->toIso8601String())->toBe('2026-10-06T23:00:00+00:00')
        ->and($fixtures[0]->status)->toBe(Fixture::UPCOMING)
        ->and($fixtures[0]->competition)->toBe('National Hockey League')
        ->and($fixtures[0]->homeLogo)->toContain('espncdn.com');
});

it('reads tennis singles of the day from a whole ESPN tournament', function (): void {
    Http::fake(['site.api.espn.com/*' => Http::response(sourceSample('espn-tennis'))]);

    $fixtures = (new EspnSource(['tennis/atp' => 'tennis']))->fixtures($this->day);

    // Пара и матч вчерашнего дня — не в счёт.
    expect($fixtures)->toHaveCount(1)
        ->and($fixtures[0]->home)->toBe('Carlos Alcaraz')
        ->and($fixtures[0]->away)->toBe('Jiri Lehecka')
        ->and($fixtures[0]->startsAt->toIso8601String())->toBe('2026-10-06T09:10:00+00:00')
        ->and($fixtures[0]->status)->toBe(Fixture::FINISHED)
        ->and($fixtures[0]->competition)->toBe('ATP')
        ->and($fixtures[0]->round)->toStartWith('Kinoshita Group Japan Open Tennis Championships · ')
        ->and([$fixtures[0]->homeScore, $fixtures[0]->awayScore])->toBe([2, 0])
        ->and($fixtures[0]->homeLogo)->toContain('/countries/');
});

it('reads a UFC card as one event named by its main fight', function (): void {
    Http::fake(['site.api.espn.com/*' => Http::response(sourceSample('espn-ufc'))]);

    $fixtures = (new EspnSource(['mma/ufc' => 'combat']))->fixtures(CarbonImmutable::parse('2026-10-24', 'UTC'));

    // Начало — основной кард (18:00), а не первый бой (14:00).
    expect($fixtures)->toHaveCount(1)
        ->and($fixtures[0]->home)->toBe('Alexander Volkanovski')
        ->and($fixtures[0]->away)->toBe('Movsar Evloev')
        ->and($fixtures[0]->startsAt->toIso8601String())->toBe('2026-10-24T18:00:00+00:00')
        ->and($fixtures[0]->competition)->toBe('UFC 333')
        ->and($fixtures[0]->status)->toBe(Fixture::UPCOMING);
});

it('reads each Formula 1 session of the day without opponents', function (): void {
    Http::fake(['site.api.espn.com/*' => Http::response(sourceSample('espn-f1'))]);
    $source = new EspnSource(['racing/f1' => 'motorsport']);

    $saturday = $source->fixtures(CarbonImmutable::parse('2026-10-03', 'UTC'));
    $race = $source->fixtures(CarbonImmutable::parse('2026-10-04', 'UTC'))[0];

    expect(array_map(fn ($session) => $session->round, $saturday))->toBe(['3-я практика', 'Квалификация'])
        ->and($race->hasTeams())->toBeFalse()
        ->and($race->title)->toBe('Gulf Air Bahrain Grand Prix in Malaysia')
        ->and($race->round)->toBe('Гонка')
        ->and($race->competition)->toBe('Formula 1')
        ->and($race->startsAt->toIso8601String())->toBe('2026-10-04T07:00:00+00:00')
        ->and($race->status)->toBe(Fixture::FINISHED);
});

it('reads biathlon races from IBU once per season and stage', function (): void {
    Http::fake([
        '*/api/Events*' => Http::response(sourceSample('ibu-events')),
        '*/api/Competitions*' => Http::response(sourceSample('ibu-competitions')),
    ]);
    $source = new IbuSource;

    $saturday = $source->fixtures(CarbonImmutable::parse('2026-11-28', 'UTC'));
    $sunday = $source->fixtures(CarbonImmutable::parse('2026-11-29', 'UTC'));

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/Events?SeasonId=2627&Level=1'));
    expect(array_map(fn ($race) => $race->title, $saturday))->toBe(['Одиночная смешанная эстафета', 'Смешанная эстафета'])
        ->and($saturday[0]->competition)->toBe('Кубок мира')
        ->and(array_map(fn ($race) => $race->title, $sunday))->toBe(['Спринт · Мужчины', 'Спринт · Женщины'])
        ->and($sunday[1]->competition)->toBe('Кубок мира · Женщины')
        ->and($sunday[1]->round)->toBe('Kontiolahti')
        ->and($sunday[1]->hasTeams())->toBeFalse()
        ->and($sunday[1]->startsAt->toIso8601String())->toBe('2026-11-29T15:15:00+00:00')
        ->and($sunday[1]->status)->toBe(Fixture::UPCOMING);
});

it('reads KHL games in Russian', function (): void {
    Http::fake(['khl.api.webcaster.pro/*' => Http::response(sourceSample('khl'))]);

    $fixtures = (new KhlSource)->fixtures($this->day);

    Http::assertSent(fn ($request) => $request['q']['start_at_gt_time_from_unixtime'] === $this->day->timestamp);
    $ska = collect($fixtures)->firstWhere('home', 'СКА');
    expect($fixtures)->toHaveCount(3)
        ->and($ska->away)->toBe('Северсталь')
        ->and($ska->status)->toBe(Fixture::UPCOMING)
        ->and($ska->startsAt->toIso8601String())->toBe('2026-10-06T16:30:00+00:00')
        ->and($ska->competition)->toBe('КХЛ');
});

it('reads one Euroleague season once for several days', function (): void {
    Http::fake(['api-live.euroleague.net/*' => Http::response(sourceSample('euroleague'))]);
    $source = new EuroleagueSource(['E' => 'Евролига']);

    $today = $source->fixtures($this->day);
    $tomorrow = $source->fixtures($this->day->addDay());

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/competitions/E/seasons/E2026/games'));
    expect($today)->toBe([])
        ->and($tomorrow)->not->toBeEmpty()
        ->and($tomorrow[0]->competition)->toBe('Евролига')
        ->and($tomorrow[0]->status)->toBe(Fixture::UPCOMING)
        ->and($tomorrow[0]->homeLogo)->not->toBeNull();
});

it('reads LiveScore days in UTC', function (): void {
    Http::fake(['prod-public-api.livescore.com/*' => Http::response(sourceSample('livescore-hockey'))]);

    $fixtures = (new LiveScoreSource(['hockey' => 'hockey']))->fixtures($this->day);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/date/hockey/20261006/0'));
    $dallas = collect($fixtures)->firstWhere('home', 'Dallas Stars');
    expect($dallas->status)->toBe(Fixture::FINISHED)
        ->and($dallas->homeScore)->toBe(5)
        ->and($dallas->startsAt->toIso8601String())->toBe('2026-10-06T00:00:00+00:00')
        ->and($dallas->homeLogo)->toBe('https://lsm-static-prod.livescore.com/medium/enet/264.png')
        ->and($dallas->competition)->toBe('NHL: NHL: Regular season');
});

it('keeps importing when one source fails', function (): void {
    Http::fake([
        'khl.api.webcaster.pro/*' => Http::response(sourceSample('khl')),
        'site.api.espn.com/*' => Http::response('Bad Request', 400),
    ]);

    $result = importer([new EspnSource(['hockey/khl' => 'hockey']), new KhlSource])->import();

    // Два дня (сегодня и завтра) — заглушка оба раза отдаёт те же три матча.
    expect($result->fixtures)->toBe(['espn' => 0, 'khl' => 6])
        ->and($result->errors)->toHaveCount(2)
        ->and($result->failed())->toBeFalse()
        ->and(Fixture::query()->where('source', 'khl')->count())->toBe(3);
});

it('fails only when every source fails', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $result = importer([new KhlSource])->import();

    expect($result->failed())->toBeTrue();
});

it('registers every enabled source', function (): void {
    config(['sports.fixtures.sources.espn.enabled' => true, 'sports.fixtures.sources.khl.enabled' => true]);

    $sources = (fn () => $this->sources)->call(app(FixtureImporter::class));

    expect(array_map(fn (FixtureSource $source) => $source->key(), $sources))->toBe(['khl', 'espn', 'thesportsdb']);
});
