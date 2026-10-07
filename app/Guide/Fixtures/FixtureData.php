<?php

namespace App\Guide\Fixtures;

use Carbon\CarbonImmutable;

/**
 * Матч в том виде, как его отдаёт источник расписания. Событие без
 * соперников (сессия Формулы-1, гонка биатлона) — без home/away, с title.
 */
final readonly class FixtureData
{
    public function __construct(
        public string $externalId,
        public string $sport,
        public ?string $home,
        public ?string $away,
        public CarbonImmutable $startsAt,
        /** Fixture::UPCOMING, LIVE, FINISHED или CANCELLED. */
        public string $status,
        public ?string $competition = null,
        public ?string $competitionLogo = null,
        public ?string $round = null,
        public ?string $homeLogo = null,
        public ?string $awayLogo = null,
        public ?int $homeScore = null,
        public ?int $awayScore = null,
        /** Название события без соперников: «Гран-при Бахрейна». */
        public ?string $title = null,
    ) {}

    public function hasTeams(): bool
    {
        return $this->home !== null && $this->away !== null;
    }
}
