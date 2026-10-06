<?php

namespace App\Guide;

use App\Guide\Data\Team;

/**
 * Эмблема-заглушка для команды из телегида: цвета и логотипа там нет,
 * поэтому цвет — из названия (у одной команды всегда один и тот же).
 */
final class TeamStyle
{
    public static function team(string $name, ?string $logo = null, bool $flag = false): Team
    {
        return new Team($name, self::shortName($name), self::color($name), $logo, $flag);
    }

    /**
     * «Манчестер Юнайтед» → «МЮ», «ЦСКА» → «ЦСК», «Динамо (Минск)» → «ДИН».
     */
    public static function shortName(string $name): string
    {
        $words = preg_split('/[\s-]+/u', trim((string) preg_replace('/\([^)]*\)/u', '', $name))) ?: [];
        $words = array_values(array_filter($words, fn (string $word) => (bool) preg_match('/^\p{L}/u', $word)));

        if (count($words) >= 2) {
            return mb_strtoupper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 3))));
        }

        return mb_strtoupper(mb_substr($words[0] ?? $name, 0, 3));
    }

    public static function color(string $name): string
    {
        $hue = crc32(mb_strtolower($name)) % 360;

        return self::hslToHex($hue, 0.62, 0.42);
    }

    private static function hslToHex(int $hue, float $saturation, float $lightness): string
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $lightness - $chroma / 2;

        [$r, $g, $b] = match (intdiv($hue, 60)) {
            0 => [$chroma, $x, 0],
            1 => [$x, $chroma, 0],
            2 => [0, $chroma, $x],
            3 => [0, $x, $chroma],
            4 => [$x, 0, $chroma],
            default => [$chroma, 0, $x],
        };

        return sprintf('#%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }
}
