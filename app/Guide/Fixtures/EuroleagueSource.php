<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Евролига и Еврокубок: API сайта euroleaguebasketball.net (без ключа).
 * Он отдаёт сразу весь сезон, поэтому сезон загружается один раз за
 * импорт, а дни выбираются из него.
 */
final class EuroleagueSource implements FixtureSource
{
    private const string URL = 'https://api-live.euroleague.net/v2/competitions';

    private const int TIMEOUT_SECONDS = 30;

    /** Сезон начинается летом: 2026-27 — «2026». */
    private const int SEASON_START_MONTH = 7;

    /** Матч ещё идёт, если не отмечен сыгранным столько после начала. */
    private const int GAME_HOURS = 3;

    /** @var array<string, list<array<string, mixed>>> */
    private array $seasons = [];

    /**
     * @param  array<string, string>  $competitions  код соревнования («E», «U») → название
     */
    public function __construct(
        private readonly array $competitions = ['E' => 'Евролига', 'U' => 'Еврокубок'],
    ) {}

    public function key(): string
    {
        return 'euroleague';
    }

    public function fixtures(CarbonImmutable $day): array
    {
        $fixtures = [];

        foreach ($this->competitions as $code => $name) {
            foreach ($this->season($code, $day) as $game) {
                $startsAt = isset($game['utcDate']) ? CarbonImmutable::parse($game['utcDate'])->utc() : null;
                if ($startsAt === null || ! $startsAt->isSameDay($day)) {
                    continue;
                }

                $fixtures[] = new FixtureData(
                    externalId: (string) $game['identifier'],
                    sport: 'basketball',
                    home: (string) $game['local']['club']['name'],
                    away: (string) $game['road']['club']['name'],
                    startsAt: $startsAt,
                    status: $this->status($game, $startsAt),
                    competition: $name,
                    round: isset($game['round']) ? "{$game['round']}-й тур" : null,
                    homeLogo: $game['local']['club']['images']['crest'] ?? null,
                    awayLogo: $game['road']['club']['images']['crest'] ?? null,
                    homeScore: isset($game['local']['score']) ? (int) $game['local']['score'] : null,
                    awayScore: isset($game['road']['score']) ? (int) $game['road']['score'] : null,
                );
            }
        }

        return $fixtures;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function season(string $code, CarbonImmutable $day): array
    {
        $year = $day->month >= self::SEASON_START_MONTH ? $day->year : $day->year - 1;
        $season = "{$code}{$year}";

        return $this->seasons[$season] ??= Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::URL."/{$code}/seasons/{$season}/games")
            ->throw()
            ->json('data') ?? [];
    }

    /**
     * @param  array<string, mixed>  $game
     */
    private function status(array $game, CarbonImmutable $startsAt): string
    {
        return match (true) {
            (bool) ($game['played'] ?? false) => Fixture::FINISHED,
            $startsAt->isFuture() => Fixture::UPCOMING,
            $startsAt->addHours(self::GAME_HOURS)->isFuture() => Fixture::LIVE,
            default => Fixture::FINISHED,
        };
    }
}
