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

    public function rebuild(CarbonImmutable $from, CarbonImmutable $to, array $parsed): int
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

        CatalogDatabase::connection()->transaction(function () use ($from, $rows): void {
            // Будущие показы пересобираются заново, прошедшие остаются (история).
            Airing::query()->where('starts_at', '>=', $from)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                Airing::query()->insert($chunk);
            }
        });

        return count($rows);
    }

    public function eventsWithAirings(CarbonImmutable $from): int
    {
        return Airing::query()->where('ends_at', '>=', $from)->distinct()->count('event_id');
    }
}
