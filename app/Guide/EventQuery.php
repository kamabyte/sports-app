<?php

namespace App\Guide;

use App\Guide\Data\EventStatus;
use Carbon\CarbonImmutable;

/**
 * Фильтр списка событий. Пустые поля не фильтруют.
 */
final readonly class EventQuery
{
    /**
     * @param  list<EventStatus>  $statuses
     */
    public function __construct(
        public ?string $sport = null,
        public ?string $competition = null,
        public array $statuses = [],
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?string $search = null,
        public ?int $limit = null,
    ) {}

    /**
     * События, которые идут в эту дату (в часовом поясе зрителя).
     */
    public static function forDay(CarbonImmutable $day, ?string $sport = null): self
    {
        return new self(sport: $sport, from: $day->startOfDay(), to: $day->endOfDay());
    }
}
