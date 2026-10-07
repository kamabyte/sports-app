<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * ESPN (site.api.espn.com, без ключа, неофициальный): scoreboard лиги за
 * день — время UTC, команды с эмблемами, счёт и состояние матча.
 * Один запрос на лигу в день.
 *
 * Не только командные лиги:
 * - теннис (tennis/atp) — турнир со всеми матчами сразу, берём одиночные этого дня;
 * - MMA (mma/ufc) — турнир целиком, соперники — главный бой, начало — основной кард;
 * - Формула-1 (racing/f1) — каждая сессия Гран-при отдельно, без соперников.
 */
final class EspnSource implements FixtureSource, ProvidesCompetitionLogos
{
    private const string URL = 'https://site.api.espn.com/apis/site/v2/sports';

    private const int TIMEOUT_SECONDS = 20;

    private const array CANCELLED = ['STATUS_POSTPONED', 'STATUS_CANCELED', 'STATUS_ABANDONED', 'STATUS_FORFEIT'];

    /** Сессии Гран-при по-русски, как в телегиде. */
    private const array SESSIONS = [
        'FP1' => '1-я практика', 'FP2' => '2-я практика', 'FP3' => '3-я практика',
        'Qual' => 'Квалификация', 'SS' => 'Спринт-квалификация', 'SQ' => 'Спринт-квалификация',
        'SR' => 'Спринт', 'Sprint' => 'Спринт', 'Race' => 'Гонка',
    ];

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
                $found = match (strtok($league, '/')) {
                    'tennis' => $this->tennis($sport, $event, $competition, $day),
                    'mma' => [$this->card($sport, $event)],
                    'racing' => $this->sessions($sport, $event, $competition, $logo, $day),
                    default => [$this->fixture($sport, $event, $competition, $logo)],
                };

                array_push($fixtures, ...array_filter($found));
            }
        }

        return $fixtures;
    }

    /**
     * Одиночные матчи теннисного турнира в этот день: ESPN отдаёт турнир
     * со всеми матчами сразу. Пары не берём — в телегиде их не называют.
     *
     * @param  array<string, mixed>  $event
     * @return list<FixtureData>
     */
    private function tennis(string $sport, array $event, ?string $competition, CarbonImmutable $day): array
    {
        $fixtures = [];

        foreach ($event['groupings'] ?? [] as $grouping) {
            if (! str_ends_with((string) ($grouping['grouping']['slug'] ?? ''), 'singles')) {
                continue;
            }

            foreach ($grouping['competitions'] ?? [] as $match) {
                if (empty($match['date']) || ! CarbonImmutable::parse($match['date'])->utc()->isSameDay($day)) {
                    continue;
                }

                [$home, $away] = $this->sides($match);
                if ($home === null || $away === null) {
                    continue;
                }

                $fixtures[] = new FixtureData(
                    externalId: (string) $match['id'],
                    sport: $sport,
                    home: (string) $home['athlete']['displayName'],
                    away: (string) $away['athlete']['displayName'],
                    startsAt: CarbonImmutable::parse($match['date'])->utc(),
                    status: $this->status($match['status']['type'] ?? []),
                    competition: $competition,
                    round: implode(' · ', array_filter([$event['name'] ?? null, $match['round']['displayName'] ?? null])),
                    homeLogo: $home['athlete']['flag']['href'] ?? null,
                    awayLogo: $away['athlete']['flag']['href'] ?? null,
                    homeScore: $this->sets($home, $away),
                    awayScore: $this->sets($away, $home),
                );
            }
        }

        return $fixtures;
    }

    /**
     * Турнир UFC — одно событие: в телегиде его называют по главному бою
     * («UFC 332: Silva vs. Wang»), а показывают с основного карда — с
     * начала последнего блока боёв.
     *
     * @param  array<string, mixed>  $event
     */
    private function card(string $sport, array $event): ?FixtureData
    {
        $bouts = $event['competitions'] ?? [];
        $main = end($bouts);
        if ($main === false || empty($main['date'])) {
            return null;
        }

        [$home, $away] = $this->sides($main);
        if ($home === null || $away === null) {
            return null;
        }

        // «UFC 333: Volkanovski vs. Evloev» → «UFC 333».
        $name = (string) ($event['name'] ?? '');

        return new FixtureData(
            externalId: (string) $event['id'],
            sport: $sport,
            home: (string) $home['athlete']['displayName'],
            away: (string) $away['athlete']['displayName'],
            startsAt: CarbonImmutable::parse($main['date'])->utc(),
            status: $this->status($event['status']['type'] ?? []),
            competition: trim(explode(':', $name)[0]) ?: null,
            homeLogo: $home['athlete']['flag']['href'] ?? null,
            awayLogo: $away['athlete']['flag']['href'] ?? null,
        );
    }

    /**
     * Сессии Гран-при в этот день — каждая отдельным событием без соперников.
     *
     * @param  array<string, mixed>  $event
     * @return list<FixtureData>
     */
    private function sessions(string $sport, array $event, ?string $competition, ?string $logo, CarbonImmutable $day): array
    {
        $fixtures = [];

        foreach ($event['competitions'] ?? [] as $session) {
            if (empty($session['date']) || ! CarbonImmutable::parse($session['date'])->utc()->isSameDay($day)) {
                continue;
            }

            $type = (string) ($session['type']['abbreviation'] ?? '');

            $fixtures[] = new FixtureData(
                externalId: (string) $session['id'],
                sport: $sport,
                home: null,
                away: null,
                startsAt: CarbonImmutable::parse($session['date'])->utc(),
                status: $this->status($session['status']['type'] ?? []),
                competition: $competition,
                competitionLogo: $logo,
                round: self::SESSIONS[$type] ?? ($type ?: null),
                title: (string) $event['name'],
            );
        }

        return $fixtures;
    }

    /**
     * Соперники матча: хозяева и гости, а в единоборствах и теннисе без
     * них — по порядку (первый — красный угол).
     *
     * @param  array<string, mixed>  $match
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>}
     */
    private function sides(array $match): array
    {
        $competitors = $match['competitors'] ?? [];
        if (count($competitors) !== 2) {
            return [null, null];
        }

        usort($competitors, fn (array $a, array $b) => match (true) {
            isset($a['homeAway'], $b['homeAway']) => $a['homeAway'] === 'home' ? -1 : 1,
            default => ($a['order'] ?? 0) <=> ($b['order'] ?? 0),
        });

        [$first, $second] = $competitors;
        if (! is_array($first) || ! is_array($second) || empty($first['athlete']['displayName']) || empty($second['athlete']['displayName'])) {
            return [null, null];
        }

        return [$first, $second];
    }

    /**
     * Выиграно сетов; null — матч ещё не начался.
     *
     * @param  array<string, mixed>  $player
     * @param  array<string, mixed>  $opponent
     */
    private function sets(array $player, array $opponent): ?int
    {
        $own = $player['linescores'] ?? [];
        if ($own === []) {
            return null;
        }

        $won = 0;
        foreach ($own as $i => $set) {
            $won += (int) ($set['winner'] ?? (($set['value'] ?? 0) > ($opponent['linescores'][$i]['value'] ?? 0)));
        }

        return $won;
    }

    /**
     * @param  array<string, mixed>  $type  status.type ESPN
     */
    private function status(array $type): string
    {
        return match (true) {
            in_array($type['name'] ?? '', self::CANCELLED, true) => Fixture::CANCELLED,
            ($type['state'] ?? '') === 'in' => Fixture::LIVE,
            ($type['state'] ?? '') === 'post' => Fixture::FINISHED,
            default => Fixture::UPCOMING,
        };
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

        return new FixtureData(
            externalId: (string) $event['id'],
            sport: $sport,
            home: (string) $home['team']['displayName'],
            away: (string) $away['team']['displayName'],
            startsAt: CarbonImmutable::parse($event['date'])->utc(),
            status: $this->status($event['status']['type'] ?? []),
            competition: $competition,
            competitionLogo: $logo,
            homeLogo: $home['team']['logo'] ?? null,
            awayLogo: $away['team']['logo'] ?? null,
            homeScore: is_numeric($home['score'] ?? null) ? (int) $home['score'] : null,
            awayScore: is_numeric($away['score'] ?? null) ? (int) $away['score'] : null,
        );
    }
}
