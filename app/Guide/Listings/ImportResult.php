<?php

namespace App\Guide\Listings;

final readonly class ImportResult
{
    /**
     * @param  list<string>  $errors  телегиды, которые не скачались (взят прежний файл или пропущены)
     */
    public function __construct(
        public int $channels,
        public int $withGuide,
        public int $programmes,
        public int $airings,
        public int $events,
        public array $errors = [],
    ) {}
}
