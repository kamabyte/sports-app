<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * LiveScore (prod-public-api.livescore.com, без ключа, неофициальный;
 * условия сайта такое использование не разрешают — для личного домашнего
 * приложения, раз в час). Много лиг и живой счёт; волейбола и гандбола нет.
 */
final readonly class LiveScoreSource implements FixtureSource
{
    private const string URL = 'https://prod-public-api.livescore.com/v1/api/app/date';

    private const string LOGOS = 'https://lsm-static-prod.livescore.com/medium/';

    private const int TIMEOUT_SECONDS = 20;

    private const array UPCOMING = ['NS', ''];

    private const array FINISHED = ['FT', 'AET', 'AP', 'AOT', 'AFTER PEN.', 'ENDED'];

    private const array CANCELLED = ['POSTP.', 'CANC.', 'ABAN.', 'ABD', 'AWARDED', 'WO'];

    /**
     * @param  array<string, string>  $sports  наш вид спорта → вид спорта LiveScore («soccer», «hockey»)
     */
    public function __construct(
        private array $sports,
    ) {}

    public function key(): string
    {
        return 'livescore';
    }

    public function fixtures(CarbonImmutable $day): array
    {
        $fixtures = [];

        foreach ($this->sports as $sport => $name) {
            // Последний сегмент — смещение от UTC: 0, время в ответе — UTC.
            $stages = Http::timeout(self::TIMEOUT_SECONDS)
                ->get(self::URL."/{$name}/{$day->format('Ymd')}/0", ['MD' => 1])
                ->throw()
                ->json('Stages') ?? [];

            foreach ($stages as $stage) {
                foreach ($stage['Events'] ?? [] as $event) {
                    $fixture = $this->fixture($sport, $stage, $event);
                    if ($fixture !== null) {
                        $fixtures[] = $fixture;
                    }
                }
            }
        }

        return $fixtures;
    }

    /**
     * @param  array<string, mixed>  $stage
     * @param  array<string, mixed>  $event
     */
    private function fixture(string $sport, array $stage, array $event): ?FixtureData
    {
        $home = $event['T1'][0] ?? null;
        $away = $event['T2'][0] ?? null;
        $startsAt = isset($event['Esd']) ? CarbonImmutable::createFromFormat('YmdHis', (string) $event['Esd'], 'UTC') : null;

        if ($home === null || $away === null || $startsAt === null) {
            return null;
        }

        $status = mb_strtoupper(trim((string) ($event['Eps'] ?? '')));

        return new FixtureData(
            // Eid уникален только внутри вида спорта: хоккейный и баскетбольный матч бывают с одним id.
            externalId: "{$sport}:{$event['Eid']}",
            sport: $sport,
            home: (string) $home['Nm'],
            away: (string) $away['Nm'],
            startsAt: $startsAt,
            status: match (true) {
                in_array($status, self::UPCOMING, true) => Fixture::UPCOMING,
                in_array($status, self::FINISHED, true) => Fixture::FINISHED,
                in_array($status, self::CANCELLED, true) => Fixture::CANCELLED,
                default => Fixture::LIVE,
            },
            competition: trim(($stage['Cnm'] ?? '').': '.($stage['Snm'] ?? ''), ': ') ?: null,
            homeLogo: isset($home['Img']) ? self::LOGOS.$home['Img'] : null,
            awayLogo: isset($away['Img']) ? self::LOGOS.$away['Img'] : null,
            homeScore: is_numeric($event['Tr1'] ?? null) ? (int) $event['Tr1'] : null,
            awayScore: is_numeric($event['Tr2'] ?? null) ? (int) $event['Tr2'] : null,
        );
    }
}
