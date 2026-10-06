<?php

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Fixtures\EspnSource;
use App\Guide\Fixtures\EuroleagueSource;
use App\Guide\Fixtures\FixtureImporter;
use App\Guide\Fixtures\FixtureSource;
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
