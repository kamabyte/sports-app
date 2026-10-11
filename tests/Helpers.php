<?php

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Matches\MatchBuilder;
use App\Guide\Teams\NationalTeams;
use App\Guide\Teams\TeamRegistry;
use App\Models\Fixture;
use App\Models\SportEvent;
use App\Sports\Catalog\CatalogWriter;
use App\Sports\Flashscore\FlashscoreImporter;
use App\Sports\Flashscore\PageFetcher;
use App\Sports\Models\Competition;
use App\Sports\Models\Event;
use App\Sports\Models\ImportRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Dispatcharr (каналы и их tvg-id) и телегид XMLTV: два русских канала с
 * прямым эфиром и повтором, студийная передача и канал без телегида.
 * Передача — [канал, начало, конец, название, описание?, подзаголовок?].
 */
function fakeDispatcharr(array $extraProgrammes = [], array $streams = [1, 2, 3], bool $guideDown = false): void
{
    $channels = [
        // Название, категория, tvg-id в плейлисте, id канала в телегиде.
        1 => ['Матч! Планета', 'Спорт', 'MatchPlaneta.ru@SD', 'MatchPlaneta.ru'],
        2 => ['Матч! Игра', 'Спорт', '2050', '2050'],
        // Без своего tvg-id Dispatcharr ставит номер канала.
        3 => ['FOX Sports', 'Спорт — мир', '3', null],
    ];

    $programmes = [
        // Прямой эфир сразу на двух каналах и повтор ночью.
        ['1', '20261006162500', '20261006180500', '▶️ Гандбол. Чемп. России. Суперлига. Мужчины. ЦСКА - "СГАУ-Саратов".'],
        ['2', '20261006162500', '20261006180500', 'Гандбол. Чемпионат России. Суперлига. Мужчины. Прямая трансляция. ЦСКА - "СГАУ-Саратов"'],
        ['2', '20261006232500', '20261007010000', 'Гандбол. Чемпионат России. Суперлига. Мужчины. ЦСКА - "СГАУ-Саратов"'],
        ['1', '20261006180500', '20261006183000', 'Что за спорт?'],
        ['1', '20261007070000', '20261007085500', 'Регби. Чемп. России. 1/2 финала. "Красный Яр" (Красноярск) - "Динамо" (Москва) (12+).'],
        ...$extraProgrammes,
    ];

    $guideChannels = array_map(fn (array $channel) => [$channel[3], $channel[0]], array_filter($channels, fn (array $channel) => $channel[3] !== null));
    fakeGuide($guideChannels, $programmes, down: $guideDown);

    $playlist = "#EXTM3U\n";
    foreach ($channels as $number => [$name, $category, $tvgId]) {
        $playlist .= "#EXTINF:-1 tvg-id=\"{$tvgId}\" tvg-name=\"{$name}\" tvg-chno=\"{$number}\" group-title=\"{$category}\",{$name}\nhttp://dispatcharr.test/proxy/ts/stream/{$number}\n";
    }

    Http::fake([
        'dispatcharr.test/player_api.php*' => function ($request) use ($channels, $streams) {
            if ($request['action'] === 'get_live_categories') {
                return Http::response([['category_id' => '53', 'category_name' => 'Спорт'], ['category_id' => '66', 'category_name' => 'Спорт — мир']]);
            }

            return Http::response(array_map(fn (int $id) => [
                'num' => $id,
                'name' => $channels[$id][0],
                'stream_id' => 100 + $id,
                'stream_icon' => "http://dispatcharr.test/logos/{$id}.png",
                'epg_channel_id' => (string) $id,
                'category_id' => $channels[$id][1] === 'Спорт' ? '53' : '66',
            ], $streams));
        },
        'dispatcharr.test/output/m3u*' => Http::response($playlist),
    ]);
}

/**
 * Один телегид XMLTV (sports.guides.sources.test) на guide.test. Каналы —
 * [ключ передачи => [id в телегиде, название]]; передачи — как в fakeDispatcharr().
 * Файл каждый раз скачивается заново (refresh_hours = 0); down — сервер телегида не отвечает.
 */
function fakeGuide(array $channels, array $programmes, string $source = 'test', bool $down = false): void
{
    config([
        'sports.guides.sources' => [$source => ['name' => 'Test', 'url' => "https://guide.test/{$source}.xml"]],
        'sports.guides.refresh_hours' => 0,
    ]);

    $xml = '<?xml version="1.0" encoding="UTF-8"?><tv>';
    foreach ($channels as [$id, $name]) {
        $xml .= '<channel id="'.htmlspecialchars($id).'"><display-name>'.htmlspecialchars($name).'</display-name></channel>';
    }
    foreach ($programmes as $programme) {
        [$key, $start, $stop, $title] = $programme;
        $id = $channels[$key][0] ?? $channels[(int) $key][0] ?? $key;
        $xml .= "<programme start=\"{$start} +0000\" stop=\"{$stop} +0000\" channel=\"".htmlspecialchars((string) $id).'"><title>'.htmlspecialchars($title).'</title>'
            .(isset($programme[5]) ? '<sub-title>'.htmlspecialchars($programme[5]).'</sub-title>' : '')
            .(isset($programme[4]) ? '<desc>'.htmlspecialchars($programme[4]).'</desc>' : '')
            .'</programme>';
    }
    $xml .= '</tv>';

    resetHttpFakes();
    Http::fake(["guide.test/{$source}.xml" => $down ? Http::response('Bad gateway', 502) : Http::response(gzencode($xml))]);
}

/** Http::fake() добавляет заглушки к прежним — для новой «версии» источника начинаем с чистого клиента. */
function resetHttpFakes(): void
{
    Http::swap(new Factory(app('events')));
}

/**
 * Матч из расписания — как его сохраняет fixtures:import: команды (сборные —
 * по стране), турнир из каталога; потом MatchBuilder собирает из него событие.
 */
function scheduleMatch(string $sport, string $home, string $away, string $startsAt, array $attributes = []): Fixture
{
    $teams = app(TeamRegistry::class);
    $competitions = app(CompetitionRegistry::class);
    $competitions->sync();
    $source = $attributes['source'] ?? 'khl';
    $competition = $competitions->resolve($sport, $attributes['competition'] ?? null);

    $national = NationalTeams::match($home, $away);
    [$homeTeam, $awayTeam] = $national !== null
        ? [$teams->national($sport, $home, $national[0]['code'], $national[0]['category'], $source), $teams->national($sport, $away, $national[1]['code'], $national[1]['category'], $source)]
        : [$teams->fromSource($sport, $home, $source, $attributes['home_logo'] ?? null), $teams->fromSource($sport, $away, $source, $attributes['away_logo'] ?? null)];

    return Fixture::query()->create([
        'source' => $source,
        'external_id' => $attributes['external_id'] ?? uniqid(),
        'sport' => $sport,
        'home' => $home,
        'away' => $away,
        'starts_at' => $startsAt,
        'status' => $attributes['status'] ?? Fixture::UPCOMING,
        'home_score' => $attributes['home_score'] ?? null,
        'away_score' => $attributes['away_score'] ?? null,
        'competition' => $attributes['competition'] ?? null,
        'competition_id' => $competition?->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
    ]);
}

/**
 * Событие без соперников из расписания: сессия Формулы-1, гонка биатлона.
 */
function scheduleRace(string $sport, string $title, string $competition, string $startsAt, array $attributes = []): Fixture
{
    $competitions = app(CompetitionRegistry::class);
    $competitions->sync();

    return Fixture::query()->create([
        'source' => $attributes['source'] ?? 'espn',
        'external_id' => $attributes['external_id'] ?? uniqid(),
        'sport' => $sport,
        'title' => $title,
        'round' => $attributes['round'] ?? null,
        'starts_at' => $startsAt,
        'status' => $attributes['status'] ?? Fixture::UPCOMING,
        'competition' => $competition,
        'competition_id' => $competitions->resolve($sport, $competition)?->id,
    ]);
}

/** Собрать события из расписания (как в конце fixtures:import). */
function buildMatches(): void
{
    (new MatchBuilder)->build(CarbonImmutable::now('UTC')->subDays(2));
}

function matchOf(Fixture $fixture): SportEvent
{
    return SportEvent::query()->findOrFail($fixture->fresh()->sport_event_id);
}

/*
 * Каталог: образцы страниц Flashscore (tests/Fixtures/sources/flashscore-pages) и импорт.
 */

function catalogPageSample(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/sources/flashscore-pages/{$name}.html"));
}

/**
 * Страницы для импорта и ответы на их адреса (en — flashscore.com, ru — flashscorekz.com).
 *
 * @param  array<string, string>  $competitions  адрес → [en-образец, ru-образец|null]
 */
function fakeCatalogPages(array $pages, array $teams = [], array $responses = []): void
{
    config([
        'sports.catalog.flashscore.competitions' => [],
        'sports.catalog.flashscore.teams' => [],
        'sports.catalog.flashscore.pause_seconds' => 0,
        'sports.catalog.flashscore.cache_hours' => 0,
    ]);
    foreach ($pages as $sport => $paths) {
        config(["sports.catalog.flashscore.competitions.{$sport}" => $paths]);
    }
    foreach ($teams as $sport => $list) {
        config(["sports.catalog.flashscore.teams.{$sport}" => $list]);
    }

    resetHttpFakes();
    Http::preventStrayRequests();
    Http::fake($responses);
}

function runCatalogImport(bool $dryRun = false, bool $translations = true): ImportRun
{
    $config = config('sports.catalog.flashscore');

    return (new FlashscoreImporter(new PageFetcher($config['hosts'], 0, (int) $config['cache_hours'], 5)))->run(dryRun: $dryRun, translations: $translations);
}

/** Плановый запуск (без списка страниц): дневные списки и бюджет страниц. */
function runCatalogImportPlanned(): ImportRun
{
    $config = config('sports.catalog.flashscore');

    return (new FlashscoreImporter(new PageFetcher($config['hosts'], 0, (int) $config['cache_hours'], 5)))->run(translations: false, planned: true);
}

/** Время по Москве, дней от «сегодня». */
function at(int $days, string $time): CarbonImmutable
{
    return CarbonImmutable::parse(CarbonImmutable::now('Europe/Moscow')->addDays($days)->toDateString().' '.$time, 'Europe/Moscow')->utc();
}

/** Небольшое расписание: футбол (клубы и сборные), парный теннис, Гран-при. */
function seedSchedule(): void
{
    $w = new CatalogWriter;
    $w->syncSports();
    $match = function (Competition $competition, array $sides, int $days, ?string $time, string $status = 'scheduled') use ($w): Event {
        $start = at($days, $time ?? '00:00');
        $event = $w->event($competition, ['kind' => 'match', 'status' => $status, 'starts_at' => $start, 'date' => $start->setTimezone('Europe/Moscow')->toDateString(), 'time_precision' => $time === null ? 'date' : 'exact']);
        $w->eventEntries($event, $sides);

        return $event;
    };
    $side = fn (int $number, $entry, ?string $label = null, ?string $status = null) => ['side' => $number, 'entry' => $entry, 'label' => $label, 'entry_status' => $status];

    // Футбол.
    $football = $w->sport('football');
    $football->update(['active' => true]);
    $england = $w->country('england', 'England', ['kind' => 'sporting_territory', 'flag_url' => 'https://flagcdn.com/w80/gb-eng.png']);
    $france = $w->country('france', 'France', ['iso2' => 'FR']);
    $italy = $w->country('italy', 'Italy', ['iso2' => 'IT']);
    $w->name($england, 'ru', 'Англия', curated: true);
    $englandCategory = $w->category($football, 'country', 'england', 'England', country: $england, position: 1);
    $w->name($englandCategory, 'ru', 'Англия', curated: true);
    $europe = $w->category($football, 'region', 'europe', 'Europe', region: $w->region('europe', 'Europe', 'Европа'));
    $pl = $w->competition($football, $englandCategory, ['name' => 'Premier League', 'slug' => 'premier-league', 'kind' => 'league', 'participant_kind' => 'club', 'position' => 1]);
    $w->name($pl, 'ru', 'Премьер-лига', curated: true);
    $unl = $w->competition($football, $europe, ['name' => 'UEFA Nations League', 'slug' => 'uefa-nations-league', 'participant_kind' => 'national']);

    $team = function (string $name, string $russian, array $attributes = []) use ($w, $football) {
        $team = $w->team($football, ['name' => $name] + $attributes);
        $w->name($team, 'ru', $russian, curated: true);

        return $w->teamEntry($team);
    };
    $arsenal = $team('Arsenal', 'Арсенал', ['kind' => 'club']);
    $chelsea = $team('Chelsea', 'Челси', ['kind' => 'club']);
    $national = fn (string $name, string $russian, array $attributes) => $team($name, $russian, $attributes + ['kind' => 'national', 'country_id' => $france->id, 'gender' => 'men', 'age_group' => 'senior', 'squad' => 'first']);
    $frenchSenior = $national('France', 'Франция', []);
    $national('France W', 'Франция (ж)', ['gender' => 'women']);
    $national('France U21', 'Франция U21', ['age_group' => 'u21']);
    $national('France Ol.', 'Франция (олимп.)', ['squad' => 'olympic']);
    $italian = $team('Italy', 'Италия', ['kind' => 'national', 'country_id' => $italy->id, 'gender' => 'men', 'age_group' => 'senior']);

    $match($pl, [$side(1, $arsenal), $side(2, $chelsea)], 0, '19:30');
    $match($pl, [$side(1, $chelsea), $side(2, $arsenal)], -1, '19:30', 'finished');
    $match($pl, [$side(1, $arsenal), $side(2, null, 'Победитель: Челси — Тоттенхэм')], 1, null);
    $match($pl, [$side(1, null, 'Brighton', 'unlisted'), $side(2, null, 'Everton', 'unlisted')], 0, '17:00');
    $match($unl, [$side(1, $frenchSenior), $side(2, $italian)], 3, '21:45');

    // Теннис: пары — заявки из двух спортсменов.
    $tennis = $w->sport('tennis');
    $tennis->update(['active' => true]);
    $atp = $w->category($tennis, 'tour', 'atp', 'ATP', position: 1);
    $doubles = $w->category($tennis, 'tour', 'atp-doubles', 'ATP - Doubles', parent: $atp);
    $shanghai = $w->competition($tennis, $doubles, ['name' => 'Shanghai (China), hard', 'slug' => 'shanghai', 'participant_kind' => 'individual', 'entry_format' => 'doubles', 'gender' => 'men']);
    $player = function (string $name, ?string $country) use ($w, $tennis) {
        $athlete = $w->athlete($tennis, ['name' => $name]);
        if ($country !== null) {
            $w->nationality($athlete, $w->country(strtolower($country), $country), null);
        }

        return $athlete;
    };
    $granollers = $player('Granollers M.', 'Spain');
    $zeballos = $player('Zeballos H.', 'Argentina');
    $medvedev = $player('Medvedev D.', null);
    $krawietz = $player('Krawietz K.', 'Germany');
    $match($shanghai, [$side(1, $w->pairEntry($tennis, $granollers, $zeballos)), $side(2, $w->pairEntry($tennis, $medvedev, $krawietz))], 1, '12:00');

    // Формула-1: этап, сессии, пилоты и их команды.
    $motorsport = $w->sport('motorsport');
    $motorsport->update(['active' => true]);
    $f1 = $w->category($motorsport, 'series', 'auto-racing-formula-1', 'Formula 1');
    $gp = $w->competition($motorsport, $f1, ['name' => 'Singapore Grand Prix', 'slug' => 'singapore-grand-prix', 'participant_kind' => 'individual']);
    $meeting = $w->event($gp, ['kind' => 'meeting', 'name' => 'Singapore Grand Prix', 'starts_at' => at(0, '12:30'), 'date' => '2026-10-07', 'status' => 'scheduled']);
    $drivers = [[$w->athleteEntry($motorsport, $w->athlete($motorsport, ['name' => 'Gasly P.'])), 'Alpine'], [$w->athleteEntry($motorsport, $w->athlete($motorsport, ['name' => 'Verstappen M.'])), 'Red Bull']];
    foreach ([['Practice 1', 0, '12:30'], ['Race', 3, '15:00']] as [$name, $days, $time]) {
        $session = $w->event($gp, ['kind' => 'session', 'parent_id' => $meeting->id, 'name' => $name, 'starts_at' => at($days, $time), 'date' => at($days, $time)->setTimezone('Europe/Moscow')->toDateString(), 'status' => 'scheduled', 'venue' => 'Marina Bay Street Circuit']);
        $w->eventEntries($session, array_map(fn (array $driver) => ['entry' => $driver[0], 'affiliation' => $driver[1]], $drivers));
    }
}

/**
 * Матч каталога со страницей турнира Flashscore (path) и названиями сторон
 * по-английски и по-русски. Теннис — спортсмены, остальное — клубы.
 *
 * @param  list<array{0: string, 1: string}>  $sides
 */
function seedCatalogMatch(string $sport, string $path, string $competition, array $sides, string $startsAt, string $kind = 'club'): Event
{
    $w = new CatalogWriter;
    $w->syncSports();
    $flashscore = $w->provider('flashscore', 'Flashscore');
    $sport = $w->sport($sport);
    $sport->update(['active' => true]);
    $category = $w->category($sport, 'series', 'category-'.$sport->key, 'Category');
    $competition = $w->competition($sport, $category, ['name' => $competition, 'participant_kind' => $sport->key === 'tennis' ? 'individual' : $kind], [$flashscore, 'path', $path]);
    $start = CarbonImmutable::parse($startsAt, 'UTC');
    $event = $w->event($competition, ['kind' => 'match', 'status' => 'scheduled', 'starts_at' => $start, 'date' => $start->toDateString(), 'time_precision' => 'exact']);
    $entries = [];
    foreach ($sides as $index => [$name, $russian]) {
        if ($sport->key === 'tennis') {
            $athlete = $w->athlete($sport, ['name' => $name]);
            $w->name($athlete, 'ru', $russian);
            $entries[] = ['side' => $index + 1, 'entry' => $w->athleteEntry($sport, $athlete)];
        } else {
            $team = $w->team($sport, ['name' => $name, 'kind' => $kind, 'gender' => 'men', 'age_group' => 'senior']);
            $w->name($team, 'ru', $russian);
            $entries[] = ['side' => $index + 1, 'entry' => $w->teamEntry($team)];
        }
    }
    $w->eventEntries($event, $entries);

    return $event;
}

/**
 * Гандбол ЦСКА — СГАУ-Саратов (в телегиде: прямой эфир на двух каналах и
 * повтор), матч КХЛ для слота «Чемпионат КХЛ», теннис и футбол без показов.
 *
 * @return array<string, Event>
 */
function seedBroadcastCatalog(): array
{
    return [
        'handball' => seedCatalogMatch('handball', '/handball/russia/superleague/', 'Superleague', [['CSKA Moscow', 'ЦСКА'], ['SGAU Saratov', 'СГАУ-Саратов']], '2026-10-06 16:30'),
        'khl' => seedCatalogMatch('hockey', '/hockey/russia/khl/', 'KHL', [['SKA St. Petersburg', 'СКА'], ['Cherepovets', 'Северсталь']], '2026-10-06 16:30'),
        'tennis' => seedCatalogMatch('tennis', '/tennis/atp-singles/shanghai/', 'Shanghai', [['Medvedev D.', 'Медведев Д.'], ['Sinner J.', 'Синнер Я.']], '2026-10-07 07:00'),
        'football' => seedCatalogMatch('football', '/football/russia/premier-league/', 'Premier League', [['Zenit', 'Зенит'], ['Spartak Moscow', 'Спартак']], '2026-10-06 18:00'),
    ];
}

function scheduleEvent(string $competition, string $home): Event
{
    return Event::query()
        ->whereHas('competition', fn ($q) => $q->where('name', $competition))
        ->whereHas('entries', fn ($q) => $q->where('side', 1)->whereHas('entry.team', fn ($t) => $t->where('name', $home)))
        ->where('status', 'scheduled')
        ->where('time_precision', 'exact')
        ->sole();
}
