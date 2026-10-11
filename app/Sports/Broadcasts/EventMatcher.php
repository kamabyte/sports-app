<?php

namespace App\Sports\Broadcasts;

use App\Guide\Competitions\CompetitionCatalog;
use App\Guide\Listings\ParsedTitle;
use App\Guide\Matches\TeamMatch;
use App\Guide\SportRegistry;
use App\Guide\TeamCategory;
use App\Sports\CatalogDatabase;
use Carbon\CarbonImmutable;

/**
 * Передача телегида — показ события каталога (или ничего). Телегид событий
 * не создаёт и ничего в каталоге не меняет: ни названий, ни написаний.
 *
 * - С соперниками («Динамо (Минск) – СКА», «Медведев – Синнер»): тот же вид
 *   спорта, обе стороны похожи, время — во время события (прямой эфир) или
 *   после (повтор).
 * - Без соперников («Чемпионат КХЛ», «Формула-1. Гонка»): единственное
 *   событие турнира из sports.guides.slots, которое начинается в эти минуты.
 */
final class EventMatcher
{
    /** Показ во время события: начинается не раньше чем за 45 минут до начала и не позже чем через 45. */
    private const int LIVE_WINDOW_MINUTES = 45;

    /** Трансляция может начаться позже стартового свистка (студия, задержка). */
    private const int KICKOFF_GRACE_MINUTES = 30;

    /** Сумма похожести двух сторон (0…2): в прямом эфире хватает 1.0, повтору нужно 1.5. */
    private const float MIN_SCORE_LIVE = 1.0;

    private const float MIN_SCORE_REPLAY = 1.5;

    /**
     * Вид спорта неизвестен («Испания. Бетис – Осасуна»): ищем во всех, но
     * строже — обе стороны должны совпасть хорошо, и второго похожего события
     * рядом быть не должно.
     */
    private const float MIN_SCORE_ANY_LIVE = 1.5;

    private const float MIN_SCORE_ANY_REPLAY = 1.8;

    private const float ANY_AMBIGUITY = 0.25;

    /** Слот «Чемпионат КХЛ»: событие начинается от 15 минут до начала слота до 45 после. */
    private const int SLOT_EARLY_MINUTES = 15;

    private const int SLOT_LATE_MINUTES = 45;

    /** Вид спорта телегида (SportRegistry) → виды спорта каталога. */
    private const array SPORTS = [
        'football' => ['football'],
        'hockey' => ['hockey'],
        'basketball' => ['basketball'],
        'volleyball' => ['volleyball'],
        'handball' => ['handball'],
        'tennis' => ['tennis'],
        'motorsport' => ['motorsport'],
        'combat' => ['boxing', 'mma'],
        'rugby' => ['rugby-union', 'rugby-league'],
        'winter' => ['winter-sports'],
        'biathlon' => ['winter-sports'],
        'cycling' => ['cycling'],
        'billiards' => ['snooker'],
        'esports' => ['esports'],
        'darts' => ['darts'],
        'cricket' => ['cricket'],
        'baseball' => ['baseball'],
        'american-football' => ['american-football'],
        'golf' => ['golf'],
        'table-tennis' => ['table-tennis'],
        'badminton' => ['badminton'],
        'other' => ['table-tennis', 'badminton'],
    ];

    /** @var array<string, list<Candidate>> по виду спорта каталога, по времени начала */
    private array $candidates = [];

    /** @var array<string, list<int>> начала тех же событий — для поиска окна делением пополам */
    private array $starts = [];

    /** @var array<string, list<int>> ключ слота → турниры каталога */
    private array $slots = [];

    /**
     * @param  array<string, list<string>>  $slotPaths  ключ турнира телегида → шаблоны адресов Flashscore
     */
    public function __construct(
        private readonly EventCandidates $events,
        private readonly array $slotPaths = [],
    ) {}

    /**
     * События, к которым можно привязать передачи телегида с этого момента.
     */
    public function load(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->candidates = [];
        $this->starts = [];
        foreach ($this->events->load($from, $to) as $sport => $list) {
            usort($list, fn (Candidate $a, Candidate $b) => $a->start <=> $b->start);
            $this->candidates[$sport] = $list;
            $this->starts[$sport] = array_map(fn (Candidate $candidate) => $candidate->start, $list);
        }
        $this->slots = $this->resolveSlots();
    }

    /**
     * @return array{event: int, live: bool}|null
     */
    public function match(ParsedTitle $parsed, CarbonImmutable $startsAt, CarbonImmutable $endsAt): ?array
    {
        // Только события, которые могут быть в этой передаче: начались не раньше
        // окна повтора и не позже окна прямого эфира.
        $from = $startsAt->getTimestamp() - EventCandidates::REPLAY_WINDOW_DAYS * 86400;
        $to = $startsAt->getTimestamp() + self::LIVE_WINDOW_MINUTES * 60;
        $candidates = [];
        $sports = $parsed->knowsSport() ? self::SPORTS[$parsed->sport] ?? [] : array_keys($this->candidates);
        foreach ($sports as $sport) {
            $starts = $this->starts[$sport] ?? [];
            for ($index = self::firstAtOrAfter($starts, $from); $index < count($starts) && $starts[$index] <= $to; $index++) {
                $candidates[] = $this->candidates[$sport][$index];
            }
        }

        $found = $parsed->hasTeams()
            ? $this->byTeams($parsed, $candidates, $startsAt, $endsAt)
            : $this->bySlot($parsed, $candidates, $startsAt);

        if ($found === null) {
            return null;
        }

        $live = ! $parsed->replay && $startsAt->lte($found->startsAt->addMinutes(self::KICKOFF_GRACE_MINUTES)) && $endsAt->gt($found->startsAt);

        return ['event' => $found->eventId, 'live' => $live];
    }

    /**
     * @param  list<Candidate>  $candidates
     */
    private function byTeams(ParsedTitle $parsed, array $candidates, CarbonImmutable $startsAt, CarbonImmutable $endsAt): ?Candidate
    {
        $category = TeamCategory::of("{$parsed->home} {$parsed->away} {$parsed->competition}");
        $homeKeys = Candidate::keys([(string) $parsed->home]);
        $awayKeys = Candidate::keys([(string) $parsed->away]);
        $known = $parsed->knowsSport();
        // Сборные играют во всех видах спорта: «Germany v Spain» без вида спорта не угадать.
        if (! $known && self::national($homeKeys) && self::national($awayKeys)) {
            return null;
        }
        [$minLive, $minReplay] = $known ? [self::MIN_SCORE_LIVE, self::MIN_SCORE_REPLAY] : [self::MIN_SCORE_ANY_LIVE, self::MIN_SCORE_ANY_REPLAY];
        $best = null;
        $bestLive = false;
        $bestRank = 0.0;
        $secondRank = 0.0;
        [$start, $end] = [$startsAt->getTimestamp(), $endsAt->getTimestamp()];

        foreach ($candidates as $candidate) {
            if (! $candidate->headToHead() || (! $candidate->individual && $candidate->category !== $category)) {
                continue;
            }

            // Закончилась до начала — студия перед матчем, а не сам матч.
            if ($end <= $candidate->start) {
                continue;
            }

            $live = ! $parsed->replay && abs($start - $candidate->start) <= self::LIVE_WINDOW_MINUTES * 60;
            $replay = $start > $candidate->start && $start <= $candidate->start + EventCandidates::REPLAY_WINDOW_DAYS * 86400;
            if (! $live && ! $replay) {
                continue;
            }

            if (! $candidate->mayBe($homeKeys, $awayKeys)) {
                continue;
            }

            $score = TeamMatch::byNames((string) $parsed->home, (string) $parsed->away, $candidate->home, $candidate->away)['score'];
            $accepted = $score >= ($live ? $minLive : $minReplay);
            // При прочих равных — событие, которое идёт в эфире.
            $rank = $score + ($live ? 1 : 0);

            if (! $accepted) {
                continue;
            }

            if ($rank > $bestRank) {
                [$best, $bestLive, $bestRank, $secondRank] = [$candidate, $live, $rank, $bestRank];
            } elseif ($rank > $secondRank) {
                $secondRank = $rank;
            }
        }

        // Без вида спорта два похожих события — не угадываем.
        if (! $known && $best !== null && $secondRank > $bestRank - self::ANY_AMBIGUITY) {
            return null;
        }

        // Повтор серии («2nd T20: India vs West Indies», плей-офф): те же соперники
        // в том же турнире играют не раз — какой из матчей показывают, не понять.
        if ($best !== null && ! $bestLive && $this->rematch($parsed, $best, $minReplay)) {
            return null;
        }

        return $best;
    }

    /** Есть ли ещё событие тех же соперников в том же турнире. */
    private function rematch(ParsedTitle $parsed, Candidate $best, float $minScore): bool
    {
        foreach ($this->candidates as $list) {
            foreach ($list as $candidate) {
                if ($candidate !== $best && $candidate->competitionId === $best->competitionId
                    && TeamMatch::byNames((string) $parsed->home, (string) $parsed->away, $candidate->home, $candidate->away)['score'] >= $minScore) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Первый индекс со значением не меньше $value (массив отсортирован).
     *
     * @param  list<int>  $values
     */
    private static function firstAtOrAfter(array $values, int $value): int
    {
        [$low, $high] = [0, count($values)];
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $values[$middle] < $value ? $low = $middle + 1 : $high = $middle;
        }

        return $low;
    }

    /**
     * @param  list<Candidate>  $candidates
     */
    private function bySlot(ParsedTitle $parsed, array $candidates, CarbonImmutable $startsAt): ?Candidate
    {
        if ($parsed->replay) {
            return null;
        }

        $key = CompetitionCatalog::match($parsed->sport, $parsed->competition);
        $competitions = $key !== null ? $this->slots[$key] ?? [] : [];
        if ($competitions === []) {
            return null;
        }

        // Слот — одно событие: матч командного вида («Чемпионат КХЛ»), гонка,
        // сессия. Теннисный турнир — много матчей сразу, ни один из них не слот.
        $category = TeamCategory::of("{$parsed->competition} {$parsed->title}");
        $inSlot = array_filter($candidates, fn (Candidate $candidate) => in_array($candidate->competitionId, $competitions, true)
            && (SportRegistry::isOneEventPerSlot($parsed->sport) || ! $candidate->headToHead())
            && $candidate->category === $category
            && $candidate->startsAt->between($startsAt->subMinutes(self::SLOT_EARLY_MINUTES), $startsAt->addMinutes(self::SLOT_LATE_MINUTES)));

        return count($inSlot) === 1 ? reset($inSlot) : null;
    }

    /**
     * @param  array<string, true>  $keys
     */
    private static function national(array $keys): bool
    {
        return $keys !== [] && array_all(array_keys($keys), fn (string $key) => str_starts_with($key, 'national:'));
    }

    /**
     * Турниры каталога для каждого слота — по адресу страницы источника.
     *
     * @return array<string, list<int>>
     */
    private function resolveSlots(): array
    {
        if ($this->slotPaths === []) {
            return [];
        }

        $paths = [];
        foreach (CatalogDatabase::connection()->table('catalog_competition_sources')->get(['competition_id', 'external_scope', 'external_id', 'url']) as $source) {
            $path = $source->external_scope === 'path' ? $source->external_id : parse_url((string) $source->url, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $paths[$path][] = (int) $source->competition_id;
            }
        }

        $slots = [];
        foreach ($this->slotPaths as $key => $patterns) {
            foreach ($paths as $path => $ids) {
                if (array_any($patterns, fn (string $pattern) => fnmatch($pattern, $path))) {
                    $slots[$key] = [...$slots[$key] ?? [], ...$ids];
                }
            }
            $slots[$key] = array_values(array_unique($slots[$key] ?? []));
        }

        return $slots;
    }
}
