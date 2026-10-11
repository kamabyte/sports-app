<?php

namespace App\Guide\Listings;

/**
 * Что удалось вынуть из названия передачи в телегиде.
 */
final readonly class ParsedTitle
{
    /** Вид спорта неизвестен: ни в названии, ни у канала его нет, а соперники есть. */
    public const string ANY_SPORT = 'any';

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

    public function knowsSport(): bool
    {
        return $this->sport !== self::ANY_SPORT;
    }

    public function hasTeams(): bool
    {
        return $this->home !== null && $this->away !== null;
    }
}
