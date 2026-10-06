<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * ESPN (site.api.espn.com, без ключа, неофициальный): scoreboard лиги за
 * день — время UTC, команды с эмблемами, счёт и состояние матча.
 * Один запрос на лигу в день.
 */
final class EspnSource implements FixtureSource, ProvidesCompetitionLogos
{
    private const string URL = 'https://site.api.espn.com/apis/site/v2/sports';

    private const int TIMEOUT_SECONDS = 20;

    private const array CANCELLED = ['STATUS_POSTPONED', 'STATUS_CANCELED', 'STATUS_ABANDONED', 'STATUS_FORFEIT'];

    /**
     * @param  array<string, string>  $leagues  путь лиги ESPN («soccer/rus.1») → наш вид спорта
     */
    public function __construct(
        private readonly array $leagues,
    ) {}

    /** @var array<string, array{sport: string, name: string, logo: string}> */
    private array $competitionLogos = [];

    public function competitionLogos(): array
    {
        return array_values($this->competitionLogos);
    }

    public function key(): string
    {
        return 'espn';
    }

    public function fixtures(CarbonImmutable $day): array
    {
        $fixtures = [];

        foreach ($this->leagues as $league => $sport) {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get(self::URL."/{$league}/scoreboard", ['dates' => $day->format('Ymd')])
                ->throw()
                ->json();

            $competition = $response['leagues'][0]['name'] ?? null;
            $logo = $response['leagues'][0]['logos'][0]['href'] ?? null;
            if ($competition !== null && $logo !== null) {
                $this->competitionLogos[$league] = ['sport' => $sport, 'name' => $competition, 'logo' => $logo];
            }

            foreach ($response['events'] ?? [] as $event) {
                $fixture = $this->fixture($sport, $event, $competition, $logo);
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
    private function fixture(string $sport, array $event, ?string $competition, ?string $logo): ?FixtureData
    {
        $competitors = [];
        foreach ($event['competitions'][0]['competitors'] ?? [] as $competitor) {
            $competitors[$competitor['homeAway'] ?? ''] = $competitor;
        }
        $home = $competitors['home'] ?? null;
        $away = $competitors['away'] ?? null;

        if ($home === null || $away === null || empty($event['date'])) {
            return null;
        }

        $status = $event['status']['type'] ?? [];
        $state = match (true) {
            in_array($status['name'] ?? '', self::CANCELLED, true) => Fixture::CANCELLED,
            ($status['state'] ?? '') === 'in' => Fixture::LIVE,
            ($status['state'] ?? '') === 'post' => Fixture::FINISHED,
            default => Fixture::UPCOMING,
        };

        return new FixtureData(
            externalId: (string) $event['id'],
            sport: $sport,
            home: (string) $home['team']['displayName'],
            away: (string) $away['team']['displayName'],
            startsAt: CarbonImmutable::parse($event['date'])->utc(),
            status: $state,
            competition: $competition,
            competitionLogo: $logo,
            homeLogo: $home['team']['logo'] ?? null,
            awayLogo: $away['team']['logo'] ?? null,
            homeScore: is_numeric($home['score'] ?? null) ? (int) $home['score'] : null,
            awayScore: is_numeric($away['score'] ?? null) ? (int) $away['score'] : null,
        );
    }
}
