<?php

namespace App\Guide\Matches;

use App\Guide\Fixtures\NameSimilarity;
use App\Guide\TeamCategory;
use App\Models\Team;

/**
 * Похожи ли две пары соперников: команды из реестра (со всеми написаниями)
 * или просто названия. Сумма похожести двух команд, 0…2; каждая — хотя бы
 * наполовину, иначе 0 («Фенербахче — Дубай» и «Reggiana — Pardubice» в одно
 * время: случайно «совпадает» одна команда).
 */
final class TeamMatch
{
    private const float MIN_TEAM_SCORE = 0.5;

    /** Перепутанные хозяева и гости (бывает в телегиде) — чуть хуже прямого совпадения. */
    private const float SWAPPED_PENALTY = 0.9;

    /**
     * @return array{score: float, swapped: bool}
     */
    public static function score(string $home, string $away, ?Team $homeTeam, ?Team $awayTeam, string $matchHome, string $matchAway): array
    {
        return self::byNames($home, $away, self::names($homeTeam, $matchHome), self::names($awayTeam, $matchAway));
    }

    /**
     * То же по спискам написаний сторон: каталог даёт их сам (названия на
     * разных языках, сокращения, спортсмены пары).
     *
     * @param  list<string>  $homeNames
     * @param  list<string>  $awayNames
     * @return array{score: float, swapped: bool}
     */
    public static function byNames(string $home, string $away, array $homeNames, array $awayNames): array
    {
        $direct = self::pair($home, $away, $homeNames, $awayNames);
        $swapped = self::pair($home, $away, $awayNames, $homeNames) * self::SWAPPED_PENALTY;

        return ['score' => max($direct, $swapped), 'swapped' => $swapped > $direct];
    }

    /**
     * Одна ли категория: «England U21» — не «Англия», женская — не мужская.
     */
    public static function sameCategory(string $left, string $right): bool
    {
        return TeamCategory::of($left) === TeamCategory::of($right);
    }

    /**
     * @param  list<string>  $homeNames
     * @param  list<string>  $awayNames
     */
    private static function pair(string $home, string $away, array $homeNames, array $awayNames): float
    {
        $homeScore = self::best($home, $homeNames);
        $awayScore = self::best($away, $awayNames);

        return $homeScore < self::MIN_TEAM_SCORE || $awayScore < self::MIN_TEAM_SCORE ? 0.0 : $homeScore + $awayScore;
    }

    /**
     * Лучшее совпадение с любым написанием стороны.
     *
     * @param  list<string>  $names
     */
    private static function best(string $name, array $names): float
    {
        return $names === [] ? 0.0 : max(array_map(fn (string $known) => NameSimilarity::score($name, $known), $names));
    }

    /**
     * Все написания команды из реестра.
     *
     * @return list<string>
     */
    private static function names(?Team $team, string $fallback): array
    {
        $names = [$fallback];
        if ($team !== null) {
            $names[] = $team->name;
            foreach ($team->aliases as $alias) {
                // «national:fr:» — служебный ключ сборной, не написание.
                if (! str_starts_with($alias->alias, 'national:')) {
                    $names[] = $alias->alias;
                }
            }
        }

        return $names;
    }
}
