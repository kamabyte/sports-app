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

    /** @param list<int>|null $channelIds */
    public function rebuild(CarbonImmutable $from, CarbonImmutable $to, array $parsed, ?array $channelIds = null): int
    {
        $this->competitions->sync();
        $airings = 0;

        DB::transaction(function () use ($from, $to, $parsed, $channelIds, &$airings): void {
            // Будущие показы пересобираются заново, прошедшие остаются (история).
            $ids = [];
            $this->matcher->load($from, $to);

            foreach ($parsed as ['programme' => $programme, 'channel' => $channel, 'parsed' => $title]) {
                $found = $this->matcher->match($title, $channel, $programme->startsAt, $programme->endsAt);

                // Не нашлось в расписании — не событие: телегид событий не создаёт.
                if ($found === null) {
                    continue;
                }

                $airing = Airing::query()->updateOrCreate([
                    'channel_id' => $channel->id,
                    'starts_at' => $programme->startsAt,
                ], [
                    'sport_event_id' => $found['match']->id,
                    'channel_id' => $channel->id,
                    'title' => $programme->title,
                    'starts_at' => $programme->startsAt,
                    'ends_at' => $programme->endsAt,
                    'live' => $found['live'],
                    'replay' => ! $found['live'],
                ]);
                $ids[] = $airing->id;
                $airings++;
            }
            Airing::query()->where('ends_at', '>', $from)->where('starts_at', '<', $to)
                ->when($channelIds !== null, fn ($query) => $query->whereIn('channel_id', $channelIds))
                ->whereNotIn('id', $ids)->delete();
        });

        return $airings;
    }

    public function eventsWithAirings(CarbonImmutable $from): int
    {
        return SportEvent::query()->where('ends_at', '>=', $from)->whereHas('airings')->count();
    }
}
