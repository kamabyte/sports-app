<?php

namespace App\Guide\Data;

/**
 * Команда или спортсмен (в теннисе и единоборствах соперники — люди).
 */
final readonly class Team
{
    public function __construct(
        public string $name,
        public string $shortName,
        public string $color,
        public ?string $logo = null,
        /** Эмблема — флаг страны (сборная): рисуется на весь круг. */
        public bool $flag = false,
    ) {}
}
