<?php

namespace App\Guide\Listings;

/**
 * Что удалось вынуть из названия передачи в телегиде.
 */
final readonly class ParsedTitle
{
    public function __construct(
        public string $sport,
        /** Матч — «Хозяева — Гости», иначе название события («Гран-при Бахрейна»). */
        public string $title,
        public ?string $home,
        public ?string $away,
        public ?string $competition,
        /** ISO-код страны турнира, если он есть в названии. */
        public ?string $country,
        public ?string $round,
        /** Помечено как прямой эфир: «▶️», «⋗», «Прямая трансляция». */
        public bool $live,
        /** Явный повтор: дата прошедшего матча «(04.10.2026)», часть записи «1–3 c». */
        public bool $replay,
    ) {}

    public function hasTeams(): bool
    {
        return $this->home !== null && $this->away !== null;
    }
}
