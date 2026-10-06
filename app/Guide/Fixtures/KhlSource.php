<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * КХЛ: бэкенд мобильного приложения (khl.api.webcaster.pro, без ключа,
 * неофициальный). Названия команд — по-русски, как в телегиде; есть эмблемы.
 */
final readonly class KhlSource implements FixtureSource
{
    private const string URL = 'https://khl.api.webcaster.pro/api/khl_mobile/events_v2';

    private const int TIMEOUT_SECONDS = 20;

    private const array UPCOMING = ['not_yet_started'];

    private const array FINISHED = ['finished', 'ended', 'game_over'];

    public function key(): string
    {
        return 'khl';
    }

    public function fixtures(CarbonImmutable $day): array
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::URL, [
                'q' => [
                    'start_at_gt_time_from_unixtime' => $day->startOfDay()->timestamp,
                    'start_at_lt_time_from_unixtime' => $day->endOfDay()->timestamp,
                ],
            ])
            ->throw()
            ->json();

        $fixtures = [];
        foreach (is_array($response) ? $response : [] as $item) {
            $fixture = $this->fixture($item['event'] ?? []);
            if ($fixture !== null) {
                $fixtures[] = $fixture;
            }
        }

        return $fixtures;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function fixture(array $event): ?FixtureData
    {
        // team_a — хозяева: «Автомобилист - Авангард», игра в Екатеринбурге.
        $home = $event['team_a']['name'] ?? null;
        $away = $event['team_b']['name'] ?? null;

        if (! $home || ! $away || ! isset($event['start_at'], $event['id'])) {
            return null;
        }

        $state = (string) ($event['game_state_key'] ?? '');
        [$homeScore, $awayScore] = array_pad(array_map('intval', explode(':', (string) ($event['score'] ?? ''))), 2, null);

        return new FixtureData(
            externalId: (string) $event['id'],
            sport: 'hockey',
            home: (string) $home,
            away: (string) $away,
            startsAt: CarbonImmutable::createFromTimestampMs((int) $event['start_at'], 'UTC'),
            status: match (true) {
                in_array($state, self::UPCOMING, true) => Fixture::UPCOMING,
                in_array($state, self::FINISHED, true) => Fixture::FINISHED,
                default => Fixture::LIVE,
            },
            competition: 'КХЛ',
            round: $event['stage_name'] ?? null,
            homeLogo: $event['team_a']['image'] ?? null,
            awayLogo: $event['team_b']['image'] ?? null,
            homeScore: $homeScore,
            awayScore: $awayScore,
        );
    }
}
