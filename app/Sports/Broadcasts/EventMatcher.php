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

    /** @var array<string, list<Candidate>> по виду спорта каталога */
    private array $candidates = [];

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
        $this->candidates = $this->events->load($from, $to);
        $this->slots = $this->resolveSlots();
    }

    /**
     * @return array{event: int, live: bool}|null
     */
    public function match(ParsedTitle $parsed, CarbonImmutable $startsAt, CarbonImmutable $endsAt): ?array
    {
        $candidates = [];
        foreach (self::SPORTS[$parsed->sport] ?? [] as $sport) {
            array_push($candidates, ...$this->candidates[$sport] ?? []);
        }

        $found = $parsed->hasTeams()
            ? $this->byTeams($parsed, $candidates, $startsAt)
            : $this->bySlot($parsed, $candidates, $startsAt);

        if ($found === null) {
            return null;
        }

        $live = $startsAt->lte($found->startsAt->addMinutes(self::KICKOFF_GRACE_MINUTES)) && $endsAt->gt($found->startsAt);

        return ['event' => $found->eventId, 'live' => $live];
    }

    /**
     * @param  list<Candidate>  $candidates
     */
    private function byTeams(ParsedTitle $parsed, array $candidates, CarbonImmutable $startsAt): ?Candidate
    {
        $category = TeamCategory::of("{$parsed->home} {$parsed->away} {$parsed->competition}");
        $homeKeys = Candidate::keys([(string) $parsed->home]);
        $awayKeys = Candidate::keys([(string) $parsed->away]);
        $best = null;
        $bestRank = 0.0;

        foreach ($candidates as $candidate) {
            if (! $candidate->headToHead() || (! $candidate->individual && $candidate->category !== $category)) {
                continue;
            }

            $live = abs($startsAt->diffInMinutes($candidate->startsAt)) <= self::LIVE_WINDOW_MINUTES;
            $replay = $startsAt->gt($candidate->startsAt) && $startsAt->lte($candidate->startsAt->addDays(EventCandidates::REPLAY_WINDOW_DAYS));
            if (! $live && ! $replay) {
                continue;
            }

            if (! $candidate->mayBe($homeKeys, $awayKeys)) {
                continue;
            }

            $score = TeamMatch::byNames((string) $parsed->home, (string) $parsed->away, $candidate->home, $candidate->away)['score'];
            $accepted = $score >= ($live ? self::MIN_SCORE_LIVE : self::MIN_SCORE_REPLAY);
            // При прочих равных — событие, которое идёт в эфире.
            $rank = $score + ($live ? 1 : 0);

            if ($accepted && $rank > $bestRank) {
                [$best, $bestRank] = [$candidate, $rank];
            }
        }

        return $best;
    }

    /**
     * @param  list<Candidate>  $candidates
     */
    private function bySlot(ParsedTitle $parsed, array $candidates, CarbonImmutable $startsAt): ?Candidate
    {
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
