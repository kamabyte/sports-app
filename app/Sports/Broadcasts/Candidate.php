<?php

namespace App\Sports\Broadcasts;

use App\Guide\Fixtures\NameSimilarity;
use Carbon\CarbonImmutable;

/**
 * Событие каталога, к которому можно привязать передачу телегида: время,
 * написания обеих сторон на всех языках и категория (женщины, U21).
 */
final readonly class Candidate
{
    /**
     * @param  list<string>  $home  написания стороны 1: команда, спортсмен, пара
     * @param  list<string>  $away  написания стороны 2
     */
    public function __construct(
        public int $eventId,
        public int $competitionId,
        public CarbonImmutable $startsAt,
        public array $home,
        public array $away,
        public string $category,
        /** Стороны — спортсмены: пол и возраст по названию не проверяются (WTA без «женщин»). */
        public bool $individual = false,
    ) {
        $this->start = $startsAt->getTimestamp();
        $this->homeKeys = self::keys($home);
        $this->awayKeys = self::keys($away);
    }

    /**
     * Могут ли эти соперники из телегида быть сторонами события (в любом
     * порядке): у каждой есть общий ключ написания. Дешёвая проверка перед
     * сравнением названий.
     *
     * @param  array<string, true>  $home
     * @param  array<string, true>  $away
     */
    public function mayBe(array $home, array $away): bool
    {
        return (array_intersect_key($home, $this->homeKeys) !== [] && array_intersect_key($away, $this->awayKeys) !== [])
            || (array_intersect_key($home, $this->awayKeys) !== [] && array_intersect_key($away, $this->homeKeys) !== []);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, true>
     */
    public static function keys(array $names): array
    {
        $keys = [];
        foreach ($names as $name) {
            foreach (NameSimilarity::keys($name) as $key) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /** Начало, Unix-время: сравнений с передачами миллионы, Carbon для них дорог. */
    public int $start;

    /** @var array<string, true> ключи написаний стороны 1 (NameSimilarity::keys) */
    public array $homeKeys;

    /** @var array<string, true> */
    public array $awayKeys;

    /** Очная встреча с известными соперниками. */
    public function headToHead(): bool
    {
        return $this->home !== [] && $this->away !== [];
    }
}
