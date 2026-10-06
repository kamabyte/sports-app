<?php

namespace App\Guide;

use App\Guide\Data\Event;
use App\Guide\Data\EventStatus;
use App\Guide\Data\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Главная — как спортивный раздел Apple TV: витрина, «Сейчас в эфире»,
 * «Скоро начнётся» и полка на каждый вид спорта. Общая для веба и ТВ-клиентов.
 */
final readonly class HomeShelves
{
    private const int FEATURED_SIZE = 6;

    private const int FEATURED_MIN_WEIGHT = 6;

    /** Больших турниров меньше — витрина из того, что есть. */
    private const int FEATURED_MIN_COUNT = 3;

    private const int FEATURED_HORIZON_HOURS = 24;

    private const int SOON_HORIZON_HOURS = 3;

    private const int SPORT_HORIZON_HOURS = 48;

    private const int SHELF_SIZE = 16;

    /**
     * @param  Collection<int, Event>  $featured
     * @param  Collection<int, Event>  $live
     * @param  Collection<int, Event>  $startingSoon
     * @param  Collection<int, array{sport: Sport, events: Collection<int, Event>}>  $sports
     */
    public function __construct(
        public Collection $featured,
        public Collection $live,
        public Collection $startingSoon,
        public Collection $sports,
    ) {}

    public static function build(EventCatalog $catalog): self
    {
        $now = CarbonImmutable::now();

        $upcoming = $catalog->events(new EventQuery(
            statuses: [EventStatus::Live, EventStatus::Upcoming],
            to: $now->addHours(self::SPORT_HORIZON_HOURS),
        ));

        $live = $upcoming
            ->filter(fn (Event $event) => $event->status() === EventStatus::Live)
            ->reject(fn (Event $event) => $event->isReplayOnly())
            ->sortByDesc(fn (Event $event) => $event->competition->weight)
            ->values();

        // Витрина — большие турниры: что идёт сейчас, потом ближайшее за сутки.
        $candidates = $upcoming
            ->reject(fn (Event $event) => $event->isReplayOnly())
            ->filter(fn (Event $event) => $event->startsAt->lte($now->addHours(self::FEATURED_HORIZON_HOURS)));
        $major = $candidates->filter(fn (Event $event) => $event->competition->weight >= self::FEATURED_MIN_WEIGHT);

        $featured = ($major->count() >= self::FEATURED_MIN_COUNT ? $major : $candidates)
            ->sortBy([
                fn (Event $a, Event $b) => ($b->status() === EventStatus::Live) <=> ($a->status() === EventStatus::Live),
                fn (Event $a, Event $b) => $b->competition->weight <=> $a->competition->weight,
                fn (Event $a, Event $b) => $a->startsAt <=> $b->startsAt,
            ])
            ->unique(fn (Event $event) => $event->competition->key)
            ->take(self::FEATURED_SIZE)
            ->values();

        $startingSoon = $upcoming
            ->filter(fn (Event $event) => $event->status() === EventStatus::Upcoming)
            ->filter(fn (Event $event) => $event->startsAt->lte($now->addHours(self::SOON_HORIZON_HOURS)))
            ->take(self::SHELF_SIZE)
            ->values();

        $sports = $catalog->sports()
            ->map(fn (Sport $sport) => [
                'sport' => $sport,
                'events' => $upcoming
                    ->filter(fn (Event $event) => $event->sport->key === $sport->key)
                    ->take(self::SHELF_SIZE)
                    ->values(),
            ])
            ->filter(fn (array $shelf) => $shelf['events']->isNotEmpty())
            ->values();

        return new self($featured, $live, $startingSoon, $sports);
    }
}
