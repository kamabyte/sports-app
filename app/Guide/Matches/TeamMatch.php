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
        $direct = self::pair($home, $away, $homeTeam, $awayTeam, $matchHome, $matchAway);
        $swapped = self::pair($home, $away, $awayTeam, $homeTeam, $matchAway, $matchHome) * self::SWAPPED_PENALTY;

        return ['score' => max($direct, $swapped), 'swapped' => $swapped > $direct];
    }

    /**
     * Одна ли категория: «England U21» — не «Англия», женская — не мужская.
     */
    public static function sameCategory(string $left, string $right): bool
    {
        return TeamCategory::of($left) === TeamCategory::of($right);
    }

    private static function pair(string $home, string $away, ?Team $homeTeam, ?Team $awayTeam, string $matchHome, string $matchAway): float
    {
        $homeScore = self::team($home, $homeTeam, $matchHome);
        $awayScore = self::team($away, $awayTeam, $matchAway);

        return $homeScore < self::MIN_TEAM_SCORE || $awayScore < self::MIN_TEAM_SCORE ? 0.0 : $homeScore + $awayScore;
    }

    /**
     * Лучшее совпадение с любым написанием команды.
     */
    private static function team(string $name, ?Team $team, string $fallback): float
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

        return max(array_map(fn (string $known) => NameSimilarity::score($name, $known), $names));
    }
}
