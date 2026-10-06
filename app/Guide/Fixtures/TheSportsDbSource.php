<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * TheSportsDB (v1): eventsday.php — матчи дня по виду спорта. Бесплатный
 * ключ «123» отдаёт только три матча на вид спорта и не чаще ~3 запросов в
 * минуту (отсюда пауза между запросами); премиум-ключ — всё.
 */
final readonly class TheSportsDbSource implements FixtureSource
{
    private const string URL = 'https://www.thesportsdb.com/api/v1/json';

    private const int TIMEOUT_SECONDS = 30;

    private const array FINISHED = ['FT', 'AET', 'PEN', 'AOT', 'AP', 'MATCH FINISHED', 'FINISHED'];

    private const array CANCELLED = ['PST', 'CANC', 'ABD', 'AWD', 'WO', 'POSTPONED', 'CANCELLED'];

    private const array UPCOMING = ['', 'NS', 'TBD', 'NOT STARTED'];

    /**
     * @param  array<string, string>  $sports  наш вид спорта → название в TheSportsDB
     */
    public function __construct(
        private string $key,
        private array $sports,
        private int $pauseSeconds = 0,
    ) {}

    public function key(): string
    {
        return 'thesportsdb';
    }

    public function fixtures(CarbonImmutable $day): array
    {
        $fixtures = [];
        $first = true;

        foreach ($this->sports as $sport => $name) {
            if (! $first && $this->pauseSeconds > 0) {
                Sleep::for($this->pauseSeconds)->seconds();
            }
            $first = false;

            $events = Http::timeout(self::TIMEOUT_SECONDS)
                ->get(self::URL."/{$this->key}/eventsday.php", ['d' => $day->toDateString(), 's' => $name])
                ->throw()
                ->json('events');

            foreach (is_array($events) ? $events : [] as $event) {
                $fixture = $this->fixture($sport, $event);
                if ($fixture !== null) {
                    $fixtures[] = $fixture;
                }
            }
        }

        return $fixtures;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function fixture(string $sport, array $event): ?FixtureData
    {
        $home = trim((string) ($event['strHomeTeam'] ?? ''));
        $away = trim((string) ($event['strAwayTeam'] ?? ''));
        // strTimestamp — UTC без пояса; у старых записей его нет, тогда дата и время.
        $timestamp = $event['strTimestamp'] ?? null ?: trim(($event['dateEvent'] ?? '').' '.($event['strTime'] ?? ''));
        $startsAt = $timestamp ? CarbonImmutable::parse($timestamp, 'UTC') : null;

        if ($home === '' || $away === '' || $startsAt === null) {
            return null;
        }

        return new FixtureData(
            externalId: (string) $event['idEvent'],
            sport: $sport,
            home: $home,
            away: $away,
            startsAt: $startsAt,
            status: $this->status((string) ($event['strStatus'] ?? '')),
            competition: ($event['strLeague'] ?? '') ?: null,
            competitionLogo: ($event['strLeagueBadge'] ?? '') ?: null,
            round: ($event['intRound'] ?? '') !== '' && ($event['intRound'] ?? null) !== null ? "{$event['intRound']}-й тур" : null,
            homeLogo: ($event['strHomeTeamBadge'] ?? '') ?: null,
            awayLogo: ($event['strAwayTeamBadge'] ?? '') ?: null,
            homeScore: is_numeric($event['intHomeScore'] ?? null) ? (int) $event['intHomeScore'] : null,
            awayScore: is_numeric($event['intAwayScore'] ?? null) ? (int) $event['intAwayScore'] : null,
        );
    }

    private function status(string $status): string
    {
        $status = mb_strtoupper(trim($status));

        return match (true) {
            in_array($status, self::UPCOMING, true) => Fixture::UPCOMING,
            in_array($status, self::FINISHED, true) => Fixture::FINISHED,
            in_array($status, self::CANCELLED, true) => Fixture::CANCELLED,
            default => Fixture::LIVE,
        };
    }
}
