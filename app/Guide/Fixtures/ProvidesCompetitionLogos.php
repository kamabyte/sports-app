<?php

namespace App\Guide\Fixtures;

/**
 * Источник знает эмблемы турниров и без матчей (ESPN отдаёт лигу и в пустой день).
 */
interface ProvidesCompetitionLogos
{
    /**
     * Эмблемы турниров, встреченные при последней загрузке.
     *
     * @return list<array{sport: string, name: string, logo: string}>
     */
    public function competitionLogos(): array;
}
