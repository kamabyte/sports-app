<?php

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use App\Guide\Fixtures\FixtureData;
use App\Guide\Fixtures\FixtureImporter;
use App\Guide\Fixtures\FixtureSource;
use App\Guide\Fixtures\FlashscoreSource;
use App\Guide\Matches\MatchBuilder;
use App\Guide\Teams\TeamRegistry;
use App\Models\Fixture;
use App\Models\SportEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

// Страницы — урезанные копии настоящих (tests/Fixtures/sources/flashscore, в начале каждой — откуда и когда).
function flashscorePage(string $name): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/sources/flashscore/{$name}.html"));
}

/** Страница дня, собранная по проверенной разметке: [время, «Хозяева - Гости», id, class, счёт]. */
function flashscoreDay(string $sport, string $day, array $rows = []): string
{
    $html = $rows === [] ? '<div><span>No match found.</span></div>' : '<h4>EUROPE: Champions League</h4>';
    foreach ($rows as [$time, $teams, $id, $class, $score]) {
        $html .= "<span>{$time}</span>{$teams} <a href=\"/match/{$id}/\" class=\"{$class}\">{$score}</a><br />";
    }

    return "<!DOCTYPE html><html><body><h2>{$sport} &raquo; {$day} &raquo; All Games</h2><div id=\"score-data\">{$html}<div class=\"cleaner\">&nbsp;</div></div></body></html>";
}

/**
 * Сайт по разделу и ?d=: [раздел => [d => ответ]]; запросы считаются в $this->requests.
 */
function fakeFlashscore(array $pages): void
{
    $requests = test()->requests;
    Http::fake(['www.flashscore.mobi/*' => function (Request $request) use ($pages, $requests) {
        $section = explode('/', parse_url($request->url(), PHP_URL_PATH) ?: '')[1] ?? '';
        $offset = (int) $request['d'];
        $requests[] = "{$section}:{$offset}";
        $page = $pages[$section][$offset] ?? null;

        return match (true) {
            $page instanceof Closure => $page(),
            is_string($page) => Http::response($page),
            default => $page ?? Http::response('Not Found', 404),
        };
    }]);
}

function byTeams(array $fixtures): array
{
    return collect($fixtures)->keyBy(fn (FixtureData $fixture) => "{$fixture->home} - {$fixture->away}")->all();
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();
    $this->requests = new ArrayObject;
    // «Сегодня» сайта — 07.10.2026, как в снятых страницах.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'UTC'));
    $this->day = CarbonImmutable::parse('2026-10-07', 'UTC');
});

it('reads handball rows with UTC kickoffs from both site days of a UTC day', function (): void {
    fakeFlashscore(['handball' => [0 => flashscorePage('handball-today'), 1 => flashscorePage('handball-tomorrow')]]);

    $fixtures = byTeams((new FlashscoreSource(['handball' => 'handball']))->fixtures($this->day));
    $kriens = $fixtures['HC Kriens - Kolstad'];

    expect($kriens->externalId)->toBe('handball:ptFwW4ng')
        ->and($kriens->startsAt->toIso8601String())->toBe('2026-10-07T16:45:00+00:00')
        ->and($kriens->status)->toBe(Fixture::UPCOMING)
        // Ссылка на таблицу в заголовке турнира — не часть названия.
        ->and($kriens->competition)->toBe('EUROPE: Champions League - Preliminary')
        ->and([$kriens->homeScore, $kriens->awayScore])->toBe([null, null])
        // 01:30 завтрашнего дня сайта — ещё сегодня по UTC; 00:30 сегодняшнего — вчера по UTC.
        ->and($fixtures['Ceske Budejovice - Jicin']->startsAt->toIso8601String())->toBe('2026-10-07T23:30:00+00:00')
        ->and($fixtures)->not->toHaveKeys(['Brno - Kopřivnice', 'Tatran Bohunice - Hranice'])
        // Сущности и неразрывные пробелы — в обычный текст, диакритика остаётся.
        ->and($fixtures)->toHaveKey('Velké Meziříčí - Holešov HC');
});

it('maps statuses and skips rows it cannot read without guessing', function (): void {
    fakeFlashscore(['handball' => [0 => flashscorePage('handball-today'), 1 => flashscorePage('handball-tomorrow')]]);
    $source = new FlashscoreSource(['handball' => 'handball']);

    $fixtures = byTeams($source->fixtures($this->day));

    expect($fixtures['Melnik - Stahlavy']->status)->toBe(Fixture::CANCELLED)
        ->and($fixtures['Melnik - Stahlavy']->startsAt->toIso8601String())->toBe('2026-10-07T18:00:00+00:00')
        ->and($fixtures['Trebon - Liberec']->status)->toBe(Fixture::CANCELLED)
        // Неявка: пометка WO — не часть названия команды.
        ->and($fixtures['Atlas W - HIFK 2 W']->status)->toBe(Fixture::CANCELLED)
        // Идущий матч без известного начала, строка без « - » и незнакомый class — пропущены.
        ->and($fixtures)->not->toHaveKey('Lok. Zagreb W - Zrinski W')
        ->and(collect($fixtures)->pluck('home'))->not->toContain('Kehra & Viimsi', 'Litovel')
        ->and(implode("\n", $source->diagnostics()))
        ->toContain('handball live without a known kickoff: 1')
        ->toContain('handball opponents not split by « - »: 1')
        ->toContain('handball unsupported status «delayed»: 1');
});

it('keeps the known kickoff of a match that is live now', function (): void {
    Fixture::query()->create([
        'source' => 'flashscore', 'external_id' => 'handball:tW0yQOUj', 'sport' => 'handball',
        'home' => 'Lok. Zagreb W', 'away' => 'Zrinski W', 'starts_at' => '2026-10-07 15:30', 'status' => Fixture::UPCOMING,
    ]);
    fakeFlashscore(['handball' => [0 => flashscorePage('handball-today'), 1 => flashscorePage('handball-tomorrow')]]);

    $zagreb = byTeams((new FlashscoreSource(['handball' => 'handball']))->fixtures($this->day))['Lok. Zagreb W - Zrinski W'];

    expect($zagreb->status)->toBe(Fixture::LIVE)
        ->and($zagreb->startsAt->toIso8601String())->toBe('2026-10-07T15:30:00+00:00')
        ->and([$zagreb->homeScore, $zagreb->awayScore])->toBe([19, 17]);
});

it('reads final scores, volleyball sets included', function (): void {
    fakeFlashscore([
        'handball' => [-1 => flashscorePage('handball-yesterday'), 0 => flashscorePage('handball-today')],
        'volleyball' => [-1 => flashscoreDay('Volleyball', 'Yesterday'), 0 => flashscorePage('volleyball-today'), 1 => flashscoreDay('Volleyball', 'Tomorrow')],
    ]);
    $source = new FlashscoreSource(['handball' => 'handball', 'volleyball' => 'volleyball']);

    $yesterday = byTeams($source->fixtures(CarbonImmutable::parse('2026-10-06', 'UTC')));
    $today = byTeams($source->fixtures($this->day));

    expect([$yesterday['Brno - Kopřivnice']->homeScore, $yesterday['Brno - Kopřivnice']->awayScore])->toBe([31, 28])
        ->and($yesterday['Brno - Kopřivnice']->status)->toBe(Fixture::FINISHED)
        ->and($yesterday['Partizan - Veszprem']->startsAt->toIso8601String())->toBe('2026-10-06T18:45:00+00:00')
        ->and($today['Vakifbank W - Eczacibasi W']->sport)->toBe('volleyball')
        ->and([$today['Vakifbank W - Eczacibasi W']->homeScore, $today['Vakifbank W - Eczacibasi W']->awayScore])->toBe([3, 2]);
});

it('fetches each site day once per import and never duplicates a match across UTC days', function (): void {
    fakeFlashscore(['handball' => [
        -1 => flashscorePage('handball-yesterday'), 0 => flashscorePage('handball-today'), 1 => flashscorePage('handball-tomorrow'),
        2 => flashscoreDay('Handball', '09.10.2026'),
    ]]);
    $source = new FlashscoreSource(['handball' => 'handball']);

    $all = [...$source->fixtures(CarbonImmutable::parse('2026-10-06', 'UTC')), ...$source->fixtures($this->day), ...$source->fixtures(CarbonImmutable::parse('2026-10-08', 'UTC'))];

    $ids = array_map(fn (FixtureData $fixture) => $fixture->externalId, $all);
    expect($ids)->toBe(array_values(array_unique($ids)))
        ->and($this->requests->getArrayCopy())->toBe(['handball:-1', 'handball:0', 'handball:1', 'handball:2']);
});

it('converts winter time after the clocks change', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00', 'UTC'));
    fakeFlashscore(['handball' => [
        6 => flashscoreDay('Handball', '26.10.2026', [['20:45', 'Kielce - SC Magdeburg', 'Ab12Cd34', 'sched', '&nbsp;-&nbsp;']]),
        7 => flashscoreDay('Handball', '27.10.2026'),
    ]]);

    $fixtures = (new FlashscoreSource(['handball' => 'handball']))->fixtures(CarbonImmutable::parse('2026-10-26', 'UTC'));

    // 26 октября в Праге уже CET (UTC+1).
    expect($fixtures[0]->startsAt->toIso8601String())->toBe('2026-10-26T19:45:00+00:00');
});

it('does not ask for days the site does not have', function (): void {
    fakeFlashscore(['handball' => [7 => flashscoreDay('Handball', '14.10.2026')]]);

    $fixtures = (new FlashscoreSource(['handball' => 'handball']))->fixtures(CarbonImmutable::parse('2026-10-14', 'UTC'));

    expect($fixtures)->toBe([])->and($this->requests->getArrayCopy())->toBe(['handball:7']);
});

it('accepts a valid empty day', function (): void {
    fakeFlashscore(['bandy' => [0 => flashscoreDay('Bandy', 'Today'), 1 => flashscorePage('bandy-empty')]]);

    expect((new FlashscoreSource(['bandy' => 'bandy']))->fixtures($this->day))->toBe([]);
});

it('treats a challenge, another layout, a wrong day or a broken list as a failed page', function (string $page, string $message): void {
    fakeFlashscore(['handball' => [0 => Http::response($page), 1 => flashscorePage('handball-tomorrow')]]);
    $source = new FlashscoreSource(['handball' => 'handball']);

    // Сегодняшняя страница не засчитана пустой: её матчей нет, сбой — в предупреждениях.
    $fixtures = byTeams($source->fixtures($this->day));

    expect(array_keys($fixtures))->toBe(['Ceske Budejovice - Jicin'])
        ->and(implode("\n", $source->diagnostics()))->toContain("2026-10-07 handball 2026-10-07: {$message}");
})->with([
    'challenge with HTTP 200' => [fn () => flashscorePage('challenge'), 'no schedule on the page'],
    'changed layout' => ['<html><body><h2>Handball &raquo; Today &raquo; All Games</h2><div id="scores"></div></body></html>', 'no schedule on the page'],
    'another day' => [fn () => flashscorePage('handball-tomorrow'), 'page shows «Tomorrow», expected «Today»'],
    'schedule without rows or marker' => ['<html><body><h2>Handball &raquo; Today &raquo; All Games</h2><div id="score-data"><p>?</p></div></body></html>', 'schedule has neither matches nor'],
]);

it('stops asking after 429 until Retry-After passes', function (): void {
    fakeFlashscore(['handball' => [0 => Http::response('Too Many Requests', 429, ['Retry-After' => '600'])]]);
    $source = new FlashscoreSource(['handball' => 'handball']);

    expect(fn () => $source->fixtures($this->day))->toThrow(RuntimeException::class, 'retry after 600 s');
    expect(fn () => (new FlashscoreSource(['handball' => 'handball']))->fixtures($this->day))->toThrow(RuntimeException::class, 'rate limited, waiting until');

    expect($this->requests->getArrayCopy())->toBe(['handball:0']);
});

it('retries a timeout a few times and then fails the day', function (): void {
    fakeFlashscore(['handball' => [0 => fn () => throw new ConnectionException('Operation timed out')]]);

    expect(fn () => (new FlashscoreSource(['handball' => 'handball']))->fixtures($this->day))->toThrow(RuntimeException::class, 'timed out');
    expect(array_count_values($this->requests->getArrayCopy())['handball:0'])->toBe(2);
});

it('keeps the sports that loaded when one sport fails', function (): void {
    fakeFlashscore([
        'handball' => [0 => flashscorePage('handball-today'), 1 => flashscorePage('handball-tomorrow')],
        'volleyball' => [0 => Http::response('Server Error', 500)],
    ]);
    $source = new FlashscoreSource(['handball' => 'handball', 'volleyball' => 'volleyball']);

    $fixtures = $source->fixtures($this->day);

    expect($fixtures)->not->toBeEmpty()
        ->and(implode("\n", $source->diagnostics()))->toContain('2026-10-07 volleyball 2026-10-07: HTTP request returned status code 500');
});

it('keeps today\'s matches when the next site day fails', function (): void {
    fakeFlashscore(['handball' => [0 => flashscorePage('handball-today'), 1 => Http::response('Server Error', 500)]]);
    $source = new FlashscoreSource(['handball' => 'handball']);

    $fixtures = byTeams($source->fixtures($this->day));

    expect($fixtures)->toHaveKey('HC Kriens - Kolstad')
        ->and($fixtures)->not->toHaveKey('Ceske Budejovice - Jicin')
        ->and(implode("\n", $source->diagnostics()))->toContain('2026-10-07 handball 2026-10-08: HTTP request returned status code 500');
});

it('fails the day only when no page loaded at all', function (): void {
    fakeFlashscore(['handball' => [0 => Http::response('Server Error', 500), 1 => Http::response('Server Error', 500)]]);

    expect(fn () => (new FlashscoreSource(['handball' => 'handball']))->fixtures($this->day))
        ->toThrow(RuntimeException::class, 'handball 2026-10-07: HTTP request returned status code 500; handball 2026-10-08');
});

it('caches pages beyond tomorrow between imports', function (): void {
    fakeFlashscore(['handball' => [
        5 => flashscoreDay('Handball', '12.10.2026', [['18:00', 'Kielce - Celje', 'Ef56Gh78', 'sched', '&nbsp;-&nbsp;']]),
        6 => flashscoreDay('Handball', '13.10.2026'),
    ]]);
    $day = CarbonImmutable::parse('2026-10-12', 'UTC');

    (new FlashscoreSource(['handball' => 'handball']))->fixtures($day);
    $again = (new FlashscoreSource(['handball' => 'handball']))->fixtures($day);

    expect($again)->toHaveCount(1)->and($this->requests->getArrayCopy())->toBe(['handball:5', 'handball:6']);
});

it('does not cache a malformed page, so the next import recovers', function (): void {
    $calls = 0;
    $malformed = '<html><body><h2>Handball &raquo; 12.10.2026 &raquo; All Games</h2><div id="score-data"><p>?</p></div></body></html>';
    $valid = flashscoreDay('Handball', '12.10.2026', [['18:00', 'Kielce - Celje', 'Ef56Gh78', 'sched', '&nbsp;-&nbsp;']]);
    fakeFlashscore(['handball' => [
        5 => function () use (&$calls, $malformed, $valid) {
            return Http::response($calls++ === 0 ? $malformed : $valid);
        },
        6 => flashscoreDay('Handball', '13.10.2026'),
    ]]);
    $day = CarbonImmutable::parse('2026-10-12', 'UTC');

    $first = new FlashscoreSource(['handball' => 'handball']);
    $broken = $first->fixtures($day);
    $recovered = (new FlashscoreSource(['handball' => 'handball']))->fixtures($day);

    expect($broken)->toBe([])
        ->and(implode("\n", $first->diagnostics()))->toContain('2026-10-12 handball 2026-10-12: schedule has neither matches nor')
        ->and($recovered)->toHaveCount(1)
        ->and($recovered[0]->home)->toBe('Kielce')
        ->and(array_count_values($this->requests->getArrayCopy())['handball:5'])->toBe(2)
        ->and(Cache::get('flashscore:handball:2026-10-12'))->toBe($valid);
});

it('evicts a malformed page that is already in the cache', function (): void {
    Cache::put('flashscore:handball:2026-10-12', '<html><body><h2>Handball &raquo; 12.10.2026 &raquo; All Games</h2><div id="score-data"></div></body></html>', now()->addHours(6));
    fakeFlashscore(['handball' => [
        5 => flashscoreDay('Handball', '12.10.2026', [['18:00', 'Kielce - Celje', 'Ef56Gh78', 'sched', '&nbsp;-&nbsp;']]),
        6 => flashscoreDay('Handball', '13.10.2026'),
    ]]);
    $day = CarbonImmutable::parse('2026-10-12', 'UTC');

    (new FlashscoreSource(['handball' => 'handball']))->fixtures($day);
    expect(Cache::has('flashscore:handball:2026-10-12'))->toBeFalse();

    $recovered = (new FlashscoreSource(['handball' => 'handball']))->fixtures($day);
    expect($recovered)->toHaveCount(1)->and($this->requests->getArrayCopy())->toContain('handball:5');
});

it('stays off unless the owner enables it', function (): void {
    $sources = (fn () => $this->sources)->call(app(FixtureImporter::class));

    expect(array_map(fn (FixtureSource $source) => $source->key(), $sources))->not->toContain('flashscore');
});

it('merges with an API match, keeps the API data and gets a broadcast through the guide', function (): void {
    config(['sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 15:00', 'UTC'));
    scheduleMatch('handball', 'Kielce', 'Magdeburg', '2026-10-07 18:45', ['source' => 'thesportsdb', 'competition' => 'EHF Champions League']);
    fakeFlashscore(['handball' => [0 => flashscorePage('handball-today'), 1 => flashscorePage('handball-tomorrow')]]);
    $importer = new FixtureImporter([new FlashscoreSource(['handball' => 'handball'])], new MatchBuilder, app(TeamRegistry::class), app(CompetitionRegistry::class), daysAhead: 0);

    $importer->import();
    $importer->import();

    $kielce = SportEvent::query()->whereHas('fixtures', fn ($query) => $query->where('external_id', 'handball:4CzZ26I6'))->sole();
    expect(Fixture::query()->where('external_id', 'handball:4CzZ26I6')->count())->toBe(1)
        ->and($kielce->fixtures()->count())->toBe(2)
        // Основной — API: его названия.
        ->and($kielce->title)->toBe('Kielce — Magdeburg')
        ->and($kielce->competition)->toBe('Лига чемпионов EHF');

    resetHttpFakes();
    fakeDispatcharr([['1', '20261007184000', '20261007204500', 'Handball. EHF Champions League. Kielce - SC Magdeburg']]);
    $this->artisan('guide:import')->assertSuccessful();

    // С показом — видно; матчи Flashscore без показа — нет.
    expect(app(EventCatalog::class)->events(new EventQuery)->pluck('title')->all())->toBe(['Kielce — Magdeburg']);
});
