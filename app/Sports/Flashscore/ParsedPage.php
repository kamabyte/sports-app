<?php

namespace App\Sports\Flashscore;

/**
 * Разобранная страница Flashscore: язык, заголовок, идентификаторы турнира
 * и блоки данных — заголовок турнира (ZA…) и его события (AA…). Значения —
 * строки как у источника; смысл полям даёт PageImporter.
 */
final readonly class ParsedPage
{
    /**
     * @param  list<array{header: array<string, string>, events: list<array<string, string>>}>  $blocks
     */
    public function __construct(
        public ?string $locale,
        public ?string $name,
        public ?string $editionLabel,
        public bool $archive,
        public ?string $templateId,
        public ?string $tournamentId,
        public ?string $stageId,
        public ?string $sportId,
        public array $blocks,
    ) {}

    public function eventCount(): int
    {
        return array_sum(array_map(fn (array $block) => count($block['events']), $this->blocks));
    }
}
