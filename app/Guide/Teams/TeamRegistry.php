<?php

namespace App\Guide\Teams;

use App\Guide\TeamCategory;
use App\Models\Team;
use App\Models\TeamAlias;

/**
 * Команды и все их написания. Источники расписания заводят команды и
 * приносят эмблемы; линкер, привязав событие телегида к матчу, добавляет
 * русское название из телегида — дальше команда узнаётся сразу, без
 * нестрогого сравнения и без матча.
 */
final class TeamRegistry
{
    /**
     * Чья эмблема лучше: у сборных — флаг; КХЛ и Евролига — официальные,
     * ESPN — крупные, LiveScore — мелкие. Меньше — лучше.
     */
    private const array LOGO_PRIORITY = ['flag' => -1, 'khl' => 0, 'euroleague' => 1, 'espn' => 2, 'thesportsdb' => 3, 'livescore' => 4];

    /** @var array<string, array<string, int>> вид спорта → написание → id команды */
    private array $aliases = [];

    /** @var array<int, Team> */
    private array $teams = [];

    /**
     * Команда из источника расписания: найти по написанию или завести.
     */
    public function fromSource(string $sport, string $name, string $source, ?string $logo): Team
    {
        $team = $this->find($sport, $name);

        if ($team === null) {
            $team = Team::query()->create(['sport' => $sport, 'name' => $name]);
            $this->teams[$team->id] = $team;
            $this->addAlias($team, $name, $source);
        }

        if ($logo !== null && $this->betterLogo($team, $source)) {
            $team->update(['logo_url' => $logo, 'logo_source' => $source, 'logo_path' => null, 'logo_failed_at' => null]);
        }

        return $team;
    }

    /**
     * Сборная: одна на вид спорта, страну и категорию («Франция», «France» и
     * «Les Bleus» из разных источников — одна команда), эмблема — флаг.
     * Вызывается, только когда страны с обеих сторон (NationalTeams::match).
     */
    public function national(string $sport, string $name, string $code, string $category, string $source): Team
    {
        $key = "national:{$code}:{$category}";
        $id = $this->aliasesOf($sport)[$key] ?? null;
        $team = $id !== null ? $this->team($id) : null;

        if ($team === null) {
            $team = Team::query()->create(['sport' => $sport, 'name' => $name]);
            $this->teams[$team->id] = $team;
            TeamAlias::query()->create(['team_id' => $team->id, 'sport' => $sport, 'alias' => $key, 'source' => 'flag']);
            $this->aliases[$sport][$key] = $team->id;
        }

        // Написание без категории («Ирландия») к молодёжной или женской сборной
        // не привязываем: оно — у взрослой мужской.
        if (TeamCategory::of($name) === $category) {
            $this->addAlias($team, $name, $source);
        }

        if ($team->logo_source !== 'flag') {
            $team->update(['logo_url' => NationalTeams::flagUrl($code), 'logo_source' => 'flag', 'logo_path' => null, 'logo_failed_at' => null]);
        }

        return $team;
    }

    public function find(string $sport, ?string $name): ?Team
    {
        if ($name === null) {
            return null;
        }

        $id = $this->aliasesOf($sport)[self::normalize($name)] ?? null;

        return $id !== null ? $this->team($id) : null;
    }

    /**
     * Название из телегида — ещё одно написание команды. По-русски оно и
     * показывается, если у команды пока только латинское.
     */
    public function learn(Team $team, string $name, string $source = 'guide'): void
    {
        // «Ирландия» из женского отбора ЧМ — не написание «Ireland Women»: иначе
        // её подставим и мужской сборной. Категорию телегид пишет в турнире.
        if (TeamCategory::of($team->name) !== TeamCategory::of($name)) {
            return;
        }

        $this->addAlias($team, $name, $source);

        if (! preg_match('/\p{Cyrillic}/u', $team->name) && preg_match('/\p{Cyrillic}/u', $name)) {
            $team->update(['name' => $name]);
        }
    }

    /**
     * «"Динамо" (Москва)» и «динамо москва» — одно написание.
     */
    public static function normalize(string $name): string
    {
        $name = str_replace('ё', 'е', mb_strtolower($name));

        return trim((string) preg_replace('/[^\p{L}\d]+/u', ' ', $name));
    }

    private function addAlias(Team $team, string $name, string $source): void
    {
        $alias = self::normalize($name);
        $aliases = $this->aliasesOf($team->sport);

        // Написание уже занято другой командой — не перехватываем.
        if ($alias === '' || isset($aliases[$alias])) {
            return;
        }

        // Кэш мог устареть (другой процесс или реестр уже добавил написание) — тогда не добавляем.
        $now = now();
        $added = TeamAlias::query()->insertOrIgnore(['team_id' => $team->id, 'sport' => $team->sport, 'alias' => $alias, 'source' => $source, 'created_at' => $now, 'updated_at' => $now]);
        if ($added > 0) {
            $this->aliases[$team->sport][$alias] = $team->id;
        }
    }

    private function betterLogo(Team $team, string $source): bool
    {
        if ($team->logo_url === null) {
            return true;
        }

        return (self::LOGO_PRIORITY[$source] ?? PHP_INT_MAX) < (self::LOGO_PRIORITY[$team->logo_source ?? ''] ?? PHP_INT_MAX);
    }

    /**
     * @return array<string, int>
     */
    private function aliasesOf(string $sport): array
    {
        return $this->aliases[$sport] ??= TeamAlias::query()->where('sport', $sport)->pluck('team_id', 'alias')->all();
    }

    private function team(int $id): ?Team
    {
        return $this->teams[$id] ??= Team::query()->find($id);
    }
}
