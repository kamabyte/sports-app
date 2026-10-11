<?php

namespace App\Sports\Broadcasts;

use App\Guide\Listings\AiringTarget;
use App\Sports\CatalogDatabase;
use App\Sports\Models\Airing;
use Carbon\CarbonImmutable;

/**
 * Показы событий каталога (catalog_airings). Передача, не узнанная как
 * событие каталога, не показывается; событие без показов видно как обычно.
 */
final readonly class CatalogAirings implements AiringTarget
{
    public function __construct(
        private EventMatcher $matcher,
    ) {}

    /** @param list<int>|null $channelIds */
    public function rebuild(CarbonImmutable $from, CarbonImmutable $to, array $parsed, ?array $channelIds = null): int
    {
        $this->matcher->load($from, $to);
        $rows = [];
        $now = CarbonImmutable::now();

        foreach ($parsed as ['programme' => $programme, 'channel' => $channel, 'parsed' => $title]) {
            $found = $this->matcher->match($title, $programme->startsAt, $programme->endsAt);
            if ($found === null) {
                continue;
            }

            $rows[] = [
                'event_id' => $found['event'],
                'channel_id' => $channel->id,
                'title' => $programme->title,
                'starts_at' => $programme->startsAt,
                'ends_at' => $programme->endsAt,
                'live' => $found['live'],
                'replay' => ! $found['live'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        CatalogDatabase::connection()->transaction(function () use ($from, $to, $rows, $channelIds): void {
            $ids = [];
            foreach ($rows as $row) {
                $airing = Airing::query()->updateOrCreate([
                    'channel_id' => $row['channel_id'],
                    'starts_at' => $row['starts_at'],
                ], array_diff_key($row, ['created_at' => true]));
                $ids[] = $airing->id;
            }
            Airing::query()->where('ends_at', '>', $from)->where('starts_at', '<', $to)
                ->when($channelIds !== null, fn ($query) => $query->whereIn('channel_id', $channelIds))
                ->whereNotIn('id', $ids)->delete();
        });

        return count($rows);
    }

    public function eventsWithAirings(CarbonImmutable $from): int
    {
        return Airing::query()->where('ends_at', '>=', $from)->distinct()->count('event_id');
    }
}
