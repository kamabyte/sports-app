<?php

namespace App\Guide\Fixtures;

use Carbon\CarbonImmutable;

/**
 * Источник расписания матчей: TheSportsDB, дальше — другие (ESPN, API-Sports…).
 */
interface FixtureSource
{
    /** Ключ источника в fixtures.source. */
    public function key(): string;

    /**
     * Матчи этого дня (UTC) по всем видам спорта, которые источник знает.
     *
     * @return list<FixtureData>
     */
    public function fixtures(CarbonImmutable $day): array;
}
