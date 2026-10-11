<?php

namespace App\Guide\Listings;

use App\Models\Channel;
use Carbon\CarbonImmutable;

/**
 * Куда привязываются разобранные передачи: события каталога (standalone)
 * или матчи прежнего приложения (iptv). Показы с начала окна телегида
 * пересобираются заново, прошедшие остаются историей.
 */
interface AiringTarget
{
    /**
     * @param  list<array{programme: Programme, channel: Channel, parsed: ParsedTitle}>  $parsed
     * @return int сколько показов привязано
     */
    public function rebuild(CarbonImmutable $from, CarbonImmutable $to, array $parsed): int;

    /** Сколько событий с показами, начиная с этого момента. */
    public function eventsWithAirings(CarbonImmutable $from): int;
}
