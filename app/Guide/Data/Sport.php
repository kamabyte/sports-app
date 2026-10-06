<?php

namespace App\Guide\Data;

final readonly class Sport
{
    public function __construct(
        public string $key,
        public string $name,
        public string $emoji,
        public string $color,
    ) {}
}
