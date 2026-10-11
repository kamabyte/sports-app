<?php

namespace App\Guide\Matches;

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Listings\AiringTarget;
use App\Models\Airing;
use App\Models\SportEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Показы матчей прежнего приложения (SPORTS_MODE=iptv): airings у sport_events.
 */
final readonly class LegacyAirings implements AiringTarget
{
    public function __construct(
        private AiringMatcher $matcher,
        private CompetitionRegistry $competitions,
    ) {}

    public function rebuild(CarbonImmutable $from, CarbonImmutable $to, array $parsed): int
    {
        $this->competitions->sync();
        $airings = 0;

        DB::transaction(function () use ($from, $to, $parsed, &$airings): void {
            // Будущие показы пересобираются заново, прошедшие остаются (история).
            Airing::query()->where('starts_at', '>=', $from)->delete();
            $this->matcher->load($from, $to);

            foreach ($parsed as ['programme' => $programme, 'channel' => $channel, 'parsed' => $title]) {
                $found = $this->matcher->match($title, $channel, $programme->startsAt, $programme->endsAt);

                // Не нашлось в расписании — не событие: телегид событий не создаёт.
                if ($found === null) {
                    continue;
                }

                Airing::query()->create([
                    'sport_event_id' => $found['match']->id,
                    'channel_id' => $channel->id,
                    'title' => $programme->title,
                    'starts_at' => $programme->startsAt,
                    'ends_at' => $programme->endsAt,
                    'live' => $found['live'],
                    'replay' => ! $found['live'],
                ]);
                $airings++;
            }
        });

        return $airings;
    }

    public function eventsWithAirings(CarbonImmutable $from): int
    {
        return SportEvent::query()->where('ends_at', '>=', $from)->whereHas('airings')->count();
    }
}
