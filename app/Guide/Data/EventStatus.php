<?php

namespace App\Guide\Data;

enum EventStatus: string
{
    case Upcoming = 'upcoming';
    case Live = 'live';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Скоро',
            self::Live => 'В эфире',
            self::Finished => 'Завершён',
        };
    }
}
