<?php

namespace App\Guide\Data;

final readonly class Score
{
    public function __construct(
        public int $home,
        public int $away,
    ) {}
}
