<?php

namespace App\Guide\Data;

final readonly class Competition
{
    public function __construct(
        public string $key,
        public string $sport,
        public string $name,
        public string $shortName,
        /** ISO-код страны или EU — для флага; null — неизвестна. */
        public ?string $country,
        /** Насколько турнир «большой»: им сортируются витрина и полки. */
        public int $weight,
        public ?string $logo = null,
    ) {}
}
