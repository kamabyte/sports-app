<?php

namespace App\Guide\Matches;

use App\Guide\CompetitionRank;
use App\Guide\SportRegistry;
use App\Models\Fixture;
use App\Models\SportEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Матчи из расписания: каждый fixture — в матч (sport_events). Один матч
 * у нескольких источников (КХЛ, ESPN, LiveScore) — один sport_events: тот же
 * вид спорта, начало в пределах 15 минут и те же команды. Событие без
 * соперников (сессия Формулы-1, гонка биатлона) ни с чем не объединяется.
 *
 * Основной источник матча — лучший по SOURCE_PRIORITY: его время, турнир и
 * стадия; у остальных — то, чего нет у основного. id матча не меняется
 * между загрузками (fixtures.sport_event_id), поэтому ссылки живут.
 */
final readonly class MatchBuilder
{
    private const array SOURCE_PRIORITY = ['khl' => 0, 'euroleague' => 1, 'ibu' => 1, 'espn' => 2, 'thesportsdb' => 3, 'livescore' => 4, 'flashscore' => 5];

    /** Один матч в двух источниках: время начала может расходиться на несколько минут. */
    private const int SAME_MATCH_MINUTES = 15;

    /** Обе команды — полностью (сумма похожести 2 из 2, с допуском на «Мг» и т. п.). */
    private const float SAME_TEAMS_SCORE = 1.5;

    /** Сколько длится матч — для «идёт сейчас». */
    private const array DURATION_MINUTES = [
        'football' => 115, 'hockey' => 150, 'basketball' => 135, 'volleyball' => 120, 'handball' => 100,
        'futsal' => 100, 'rugby' => 110, 'waterpolo' => 90, 'bandy' => 120,
        // Турнир UFC — основной кард целиком; гонка биатлона — около часа.
        'tennis' => 120, 'combat' => 180, 'motorsport' => 120, 'biathlon' => 80,
    ];

    private const int DEFAULT_DURATION_MINUTES = 120;

    /** Прошедшие матчи храним столько — к ним цепляются повторы. */
    private const int KEEP_DAYS = 14;

    /**
     * @return int матчей в окне
     */
    public function build(CarbonImmutable $from): int
    {
        $fixtures = Fixture::query()
            ->with(['homeTeam.aliases', 'awayTeam.aliases', 'competitionModel'])
            ->where('starts_at', '>=', $from)
            ->where('status', '!=', Fixture::CANCELLED)
            ->get()
            ->sortBy(fn (Fixture $fixture) => [self::SOURCE_PRIORITY[$fixture->source] ?? PHP_INT_MAX, $fixture->id])
            ->values();

        $matches = [];
        $buckets = [];
        foreach (SportEvent::query()->where('starts_at', '>=', $from->subDay())->get() as $match) {
            $this->remember($matches, $buckets, $match);
        }
        $primary = [];

        foreach ($fixtures as $fixture) {
            $match = $this->find($matches, $buckets, $fixture);
            if ($match === null) {
                $match = $this->create($fixture);
                $this->remember($matches, $buckets, $match);
            }

            if ($fixture->sport_event_id !== $match->id) {
                $fixture->update(['sport_event_id' => $match->id]);
            }

            // Первый — лучший источник; остальные только дополняют.
            $this->describe($match, $fixture, isset($primary[$match->id]));
            $primary[$match->id] = true;
        }

        // Матч, которого больше нет ни в одном источнике (перенесли, отменили), — убираем.
        SportEvent::query()->where('starts_at', '>=', $from)->whereDoesntHave('fixtures')->delete();
        SportEvent::query()->where('starts_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        return count($matches);
    }

    /**
     * Матчи — по виду спорта и 15-минутке начала: кандидаты на «тот же матч»
     * ищутся только рядом, а не среди тысяч матчей недели.
     *
     * @param  array<int, SportEvent>  $matches
     * @param  array<string, list<SportEvent>>  $buckets
     */
    private function remember(array &$matches, array &$buckets, SportEvent $match): void
    {
        $matches[$match->id] = $match;
        $buckets[$this->bucket($match->sport, $match->starts_at->getTimestamp())][] = $match;
    }

    private function bucket(string $sport, int $timestamp): string
    {
        return $sport.':'.intdiv($timestamp, self::SAME_MATCH_MINUTES * 60);
    }

    /**
     * @param  array<int, SportEvent>  $matches
     * @param  array<string, list<SportEvent>>  $buckets
     */
    private function find(array $matches, array $buckets, Fixture $fixture): ?SportEvent
    {
        if ($fixture->sport_event_id !== null && isset($matches[$fixture->sport_event_id])) {
            return $matches[$fixture->sport_event_id];
        }

        // Событие без соперников (сессия, гонка) — своё у каждого источника: сравнивать не с чем.
        if ($fixture->home === null || $fixture->away === null) {
            return null;
        }

        $time = $fixture->starts_at->getTimestamp();
        $step = self::SAME_MATCH_MINUTES * 60;

        foreach ([$time - $step, $time, $time + $step] as $near) {
            foreach ($buckets[$this->bucket($fixture->sport, $near)] ?? [] as $match) {
                if (abs($match->starts_at->diffInMinutes($fixture->starts_at)) <= self::SAME_MATCH_MINUTES && $this->sameTeams($match, $fixture)) {
                    return $match;
                }
            }
        }

        return null;
    }

    private function sameTeams(SportEvent $match, Fixture $fixture): bool
    {
        if ($match->home === null || $match->away === null) {
            return false;
        }

        if ($match->home_team_id !== null && $match->home_team_id === $fixture->home_team_id && $match->away_team_id === $fixture->away_team_id) {
            return true;
        }

        if (! TeamMatch::sameCategory("{$match->home} {$match->away}", "{$fixture->home} {$fixture->away}")) {
            return false;
        }

        $score = TeamMatch::score((string) $match->home, (string) $match->away, $fixture->homeTeam, $fixture->awayTeam, $fixture->home, $fixture->away);

        return ! $score['swapped'] && $score['score'] >= self::SAME_TEAMS_SCORE;
    }

    private function create(Fixture $fixture): SportEvent
    {
        return SportEvent::query()->create([
            'fixture_id' => $fixture->id,
            'sport' => $fixture->sport,
            'title' => $this->title($fixture),
            'home' => $fixture->homeTeam->name ?? $fixture->home,
            'away' => $fixture->awayTeam->name ?? $fixture->away,
            'starts_at' => $fixture->starts_at,
            'ends_at' => $fixture->starts_at->copy()->addMinutes(self::DURATION_MINUTES[$fixture->sport] ?? self::DEFAULT_DURATION_MINUTES),
            'search' => '',
        ]);
    }

    /**
     * Время, команды, турнир и стадия — от основного источника; если это
     * дополнительный, — только то, чего у матча ещё нет.
     */
    private function describe(SportEvent $match, Fixture $fixture, bool $secondary): void
    {
        if (! $secondary) {
            $match->fill([
                'fixture_id' => $fixture->id,
                'starts_at' => $fixture->starts_at,
                'ends_at' => $fixture->starts_at->copy()->addMinutes(self::DURATION_MINUTES[$fixture->sport] ?? self::DEFAULT_DURATION_MINUTES),
                'home_team_id' => $fixture->home_team_id,
                'away_team_id' => $fixture->away_team_id,
            ]);

            // Русские названия из телегида (их ставит AiringMatcher) не затираем.
            if (! $this->cyrillic($match->home)) {
                $match->home = $fixture->homeTeam->name ?? $fixture->home;
            }
            if (! $this->cyrillic($match->away)) {
                $match->away = $fixture->awayTeam->name ?? $fixture->away;
            }
        }

        $competition = $fixture->competitionModel;
        if ($competition !== null && $match->competition_id === null) {
            $match->fill([
                'competition_id' => $competition->id,
                'competition' => $competition->name,
                'competition_key' => $competition->key,
                'weight' => $competition->weight,
                'country' => $competition->country,
            ]);
        } elseif ($match->competition === null && $fixture->competition !== null) {
            $match->fill([
                'competition' => $fixture->competition,
                'competition_key' => Str::slug("{$fixture->sport} {$fixture->competition}"),
                'weight' => CompetitionRank::of($fixture->sport, $fixture->competition)['weight'],
            ]);
        }

        $match->round ??= $fixture->round;
        if ($match->home !== null) {
            $match->title = "{$match->home} — {$match->away}";
        } elseif (! $secondary && ! $this->cyrillic($match->title)) {
            // Русское название гонки из телегида (его ставит AiringMatcher) не затираем.
            $match->title = $this->title($fixture);
        }
        $match->search = $this->searchText($match, $fixture);

        if ($match->isDirty()) {
            $match->save();
        }
    }

    private function title(Fixture $fixture): string
    {
        return $fixture->home !== null ? "{$fixture->home} — {$fixture->away}" : (string) $fixture->title;
    }

    /**
     * По чему ищут: названия по-русски и по-английски, все написания команд, турнир, вид спорта.
     */
    private function searchText(SportEvent $match, Fixture $fixture): string
    {
        $words = [$match->title, $match->competition, SportRegistry::find($match->sport)?->name, $fixture->home, $fixture->away];
        foreach ([$fixture->homeTeam, $fixture->awayTeam] as $team) {
            foreach ($team->aliases ?? [] as $alias) {
                if (! str_starts_with($alias->alias, 'national:')) {
                    $words[] = $alias->alias;
                }
            }
        }

        $existing = $match->search !== '' ? explode(' | ', $match->search) : [];

        return implode(' | ', array_values(array_unique(array_filter([...$existing, ...array_map(fn ($word) => mb_strtolower((string) $word), $words)]))));
    }

    private function cyrillic(?string $text): bool
    {
        return $text !== null && (bool) preg_match('/\p{Cyrillic}/u', $text);
    }
}
