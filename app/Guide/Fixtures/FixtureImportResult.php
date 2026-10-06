<?php

namespace App\Guide\Fixtures;

final readonly class FixtureImportResult
{
    /**
     * @param  array<string, int>  $fixtures  источник → сколько матчей загружено
     * @param  list<string>  $errors  какие источники и дни не загрузились
     */
    public function __construct(
        public array $fixtures,
        /** Матчей (событий) в окне после сборки. */
        public int $matches,
        public array $errors = [],
    ) {}

    public function failed(): bool
    {
        return $this->errors !== [] && array_sum($this->fixtures) === 0;
    }
}
