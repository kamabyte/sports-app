<?php

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Matches\MatchBuilder;
use App\Guide\Teams\NationalTeams;
use App\Guide\Teams\TeamRegistry;
use App\Models\Fixture;
use App\Models\SportEvent;
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
