<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Биатлон: открытый API результатов IBU (biathlonresults.com, без ключа).
 * Этапы сезона (Кубок мира, чемпионат мира) — один запрос на сезон, гонки
 * этапа — один запрос на этап; оба запоминаются на всю загрузку.
 * Гонка — событие без соперников: «Спринт · Мужчины», турнир «Кубок мира».
 */
final class IbuSource implements FixtureSource
{
    private const string URL = 'https://www.biathlonresults.com/modules/sportapi/api';

    private const int TIMEOUT_SECONDS = 20;

    /** Уровень 1 — Кубок мира, чемпионаты мира и Олимпиада. */
    private const int WORLD_CUP_LEVEL = 1;

    /** Дисциплины по-русски, как в телегиде. */
    private const array DISCIPLINES = [
        'SP' => 'Спринт', 'PU' => 'Гонка преследования', 'IN' => 'Индивидуальная гонка',
        'SI' => 'Короткая индивидуальная гонка', 'MS' => 'Масс-старт', 'RL' => 'Эстафета',
        'SR' => 'Одиночная смешанная эстафета',
    ];

    /** Пол гонки: SM — мужчины, SW — женщины, MX — смешанная. */
    private const array CATEGORIES = ['SM' => 'Мужчины', 'SW' => 'Женщины'];

    /** @var array<string, list<array<string, mixed>>> этапы по сезону */
    private array $events = [];

    /** @var array<string, list<array<string, mixed>>> гонки по этапу */
    private array $races = [];

    public function key(): string
    {
        return 'ibu';
    }

    public function fixtures(CarbonImmutable $day): array
    {
        $fixtures = [];

        foreach ($this->events($this->season($day)) as $event) {
            $from = CarbonImmutable::parse($event['StartDate'])->utc()->startOfDay();
            $to = CarbonImmutable::parse($event['EndDate'])->utc()->endOfDay();
            if (! $day->between($from, $to)) {
                continue;
            }

            foreach ($this->races((string) $event['EventId']) as $race) {
                $startsAt = CarbonImmutable::parse($race['StartTime'])->utc();
                if ($startsAt->isSameDay($day)) {
                    $fixtures[] = $this->fixture($event, $race, $startsAt);
                }
            }
        }

        return $fixtures;
    }

    /**
     * Сезон IBU — «2627» для зимы 2026/27; новый начинается летом.
     */
    private function season(CarbonImmutable $day): string
    {
        $start = $day->month >= 7 ? $day->year : $day->year - 1;

        return sprintf('%02d%02d', $start % 100, ($start + 1) % 100);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(string $season): array
    {
        return $this->events[$season] ??= Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::URL.'/Events', ['SeasonId' => $season, 'Level' => self::WORLD_CUP_LEVEL])
            ->throw()
            ->json() ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function races(string $event): array
    {
        return $this->races[$event] ??= Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::URL.'/Competitions', ['EventId' => $event])
            ->throw()
            ->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $race
     */
    private function fixture(array $event, array $race, CarbonImmutable $startsAt): FixtureData
    {
        $category = self::CATEGORIES[$race['catId'] ?? ''] ?? null;
        $discipline = $race['DisciplineId'] ?? '';
        $name = self::DISCIPLINES[$discipline] ?? (string) $race['ShortDescription'];
        if ($discipline === 'RL' && $category === null) {
            $name = 'Смешанная эстафета';
        }

        // Женские гонки — отдельный турнир каталога (TeamCategory), смешанные — с мужскими.
        $competition = match (true) {
            str_contains((string) ($event['Description'] ?? ''), 'World Championships') => 'Чемпионат мира',
            str_contains((string) ($event['Description'] ?? ''), 'Olympic') => 'Олимпийские игры',
            default => 'Кубок мира',
        };

        return new FixtureData(
            externalId: (string) $race['RaceId'],
            sport: 'biathlon',
            home: null,
            away: null,
            startsAt: $startsAt,
            status: $this->status($race),
            competition: $category !== null ? "{$competition} · {$category}" : $competition,
            round: (string) ($event['ShortDescription'] ?? '') ?: null,
            title: $category !== null ? "{$name} · {$category}" : $name,
        );
    }

    /**
     * @param  array<string, mixed>  $race
     */
    private function status(array $race): string
    {
        return match (true) {
            in_array($race['ScheduleStatus'] ?? '', ['CANCELLED', 'POSTPONED'], true) => Fixture::CANCELLED,
            (bool) ($race['IsLive'] ?? false) => Fixture::LIVE,
            ($race['ScheduleStatus'] ?? '') === 'FINISHED' || in_array($race['ResultStatus'] ?? '', ['OFFICIAL', 'UNOFFICIAL'], true) => Fixture::FINISHED,
            default => Fixture::UPCOMING,
        };
    }
}
