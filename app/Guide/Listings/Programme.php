<?php

namespace App\Guide\Listings;

use Carbon\CarbonImmutable;

/**
 * Передача из телегида XMLTV.
 */
final readonly class Programme
{
    /** Длинное описание — пересказ передачи, а не «турнир, соперники». */
    private const int MAX_DETAIL_LENGTH = 200;

    /** Строки описания, в которых нет ничего о матче. */
    private const string CREDITS = '/^(comentator|comentatori|producator|producător|prezinta|prezintă|commentator|komentuje|komentář|kommentátor)\b/iu';

    public function __construct(
        public string $source,
        public string $channel,
        public string $title,
        public ?string $subTitle,
        public ?string $description,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}

    /**
     * Где ещё искать соперников, если в названии только вид спорта:
     *
     *   Kézilabda | (Európa-liga, férfiak, csoportkör, élő, HD, Mors-Thy Handbold - MOL Tatabánya KC)
     *   Lední hokej | CHL, základní část Bílí Tygři Liberec - Frölunda.
     *   WNBA Basketball | Atlanta Dream at Washington Mystics
     *   Handbal masculin | LIGA ZIMBRILOR ET4 ⏎ AHC Potaissa Turda-CSU Suceava ⏎ Comentator: …
     *
     * @return list<string>
     */
    public function details(): array
    {
        return array_values(array_filter([
            $this->clean($this->subTitle),
            $this->clean($this->description),
        ]));
    }

    private function clean(?string $text): ?string
    {
        $lines = preg_split('/\R/u', trim((string) $text)) ?: [];
        $lines = array_filter(array_map('trim', $lines), fn (string $line) => $line !== '' && ! preg_match(self::CREDITS, $line));
        // «(… , Mors-Thy Handbold - MOL Tatabánya KC).» — всё описание в скобках.
        $text = trim((string) preg_replace('/^\((.*)\)\.?$/su', '$1', implode('. ', $lines)));

        return $text !== '' && mb_strlen($text) <= self::MAX_DETAIL_LENGTH ? $text : null;
    }
}
