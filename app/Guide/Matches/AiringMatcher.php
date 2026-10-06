<?php

namespace App\Guide\Matches;

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Listings\ParsedTitle;
use App\Guide\SportRegistry;
use App\Guide\TeamCategory;
use App\Guide\Teams\TeamRegistry;
use App\Models\Channel;
use App\Models\SportEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Передача телегида — показ матча из расписания (или ничего). Телегид
 * событий не создаёт: что не нашлось в расписании, не показывается.
 *
 * - С соперниками («Динамо (Минск) – СКА»): тот же вид спорта, похожие
 *   команды и время — во время матча (прямой эфир) или после (повтор).
 * - Без соперников («Чемпионат КХЛ»): матч того же турнира, который
 *   начинается в эти минуты, если он такой один.
 */
final class AiringMatcher
{
    /** Показ во время матча: начинается не раньше чем за 45 минут до начала и не позже чем через 45. */
    private const int LIVE_WINDOW_MINUTES = 45;

    /** Трансляция может начаться позже стартового свистка (студия, задержка). */
    private const int KICKOFF_GRACE_MINUTES = 30;

    /** Повтор — в пределах двух недель после матча. */
    private const int REPLAY_WINDOW_DAYS = 14;

    /** Сумма похожести двух команд (0…2): в прямом эфире хватает 1.0, повтору нужно 1.5. */
    private const float MIN_SCORE_LIVE = 1.0;

    private const float MIN_SCORE_REPLAY = 1.5;

    /** Слот «Чемпионат КХЛ»: матч начинается от 15 минут до начала слота до 45 после. */
    private const int SLOT_EARLY_MINUTES = 15;

    private const int SLOT_LATE_MINUTES = 45;

    /** @var array<string, list<SportEvent>> по виду спорта */
    private array $matches = [];

    public function __construct(
        private readonly TeamRegistry $teams,
        private readonly CompetitionRegistry $competitions,
    ) {}

    /**
     * Матчи, к которым можно привязать передачи телегида с этого момента.
     */
    public function load(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->matches = [];
        $matches = SportEvent::query()
            ->with(['homeTeam.aliases', 'awayTeam.aliases'])
            ->where('starts_at', '>=', $from->subDays(self::REPLAY_WINDOW_DAYS))
            ->where('starts_at', '<=', $to)
            ->get();

        foreach ($matches as $match) {
            $this->matches[$match->sport][] = $match;
        }
    }

    /**
     * @return array{match: SportEvent, live: bool}|null
     */
    public function match(ParsedTitle $parsed, Channel $channel, CarbonImmutable $startsAt, CarbonImmutable $endsAt): ?array
    {
        $candidates = collect($this->matches[$parsed->sport] ?? []);

        $match = $parsed->hasTeams()
            ? $this->byTeams($parsed, $candidates, $startsAt)
            : $this->bySlot($parsed, $candidates, $startsAt);

        if ($match === null) {
            return null;
        }

        $kickoff = CarbonImmutable::instance($match->starts_at);
        $live = $startsAt->lte($kickoff->addMinutes(self::KICKOFF_GRACE_MINUTES)) && $endsAt->gt($kickoff);

        return ['match' => $match, 'live' => $live];
    }

    /**
     * @param  Collection<int, SportEvent>  $candidates
     */
    private function byTeams(ParsedTitle $parsed, Collection $candidates, CarbonImmutable $startsAt): ?SportEvent
    {
        $category = TeamCategory::of("{$parsed->home} {$parsed->away} {$parsed->competition}");
        $best = null;
        $bestRank = 0.0;
        $bestSwapped = false;

        foreach ($candidates as $match) {
            if (TeamCategory::of("{$match->homeTeam?->name} {$match->awayTeam?->name}") !== $category) {
                continue;
            }

            $kickoff = CarbonImmutable::instance($match->starts_at);
            $live = abs($startsAt->diffInMinutes($kickoff)) <= self::LIVE_WINDOW_MINUTES;
            $replay = $startsAt->gt($kickoff) && $startsAt->lte($kickoff->addDays(self::REPLAY_WINDOW_DAYS));

            if (! $live && ! $replay) {
                continue;
            }

            $score = TeamMatch::score((string) $parsed->home, (string) $parsed->away, $match->homeTeam, $match->awayTeam, (string) $match->home, (string) $match->away);
            $accepted = $live ? $score['score'] >= self::MIN_SCORE_LIVE : $score['score'] >= self::MIN_SCORE_REPLAY;
            // При прочих равных — матч, который идёт в эфире.
            $rank = $score['score'] + ($live ? 1 : 0);

            if ($accepted && $rank > $bestRank) {
                [$best, $bestRank, $bestSwapped] = [$match, $rank, $score['swapped']];
            }
        }

        if ($best !== null) {
            $this->learn($best, $parsed, $bestSwapped);
        }

        return $best;
    }

    /**
     * @param  Collection<int, SportEvent>  $candidates
     */
    private function bySlot(ParsedTitle $parsed, Collection $candidates, CarbonImmutable $startsAt): ?SportEvent
    {
        // Слот без команд бывает только у командных видов: «Чемпионат КХЛ».
        // Теннисный турнир или гонку сопоставлять не с чем.
        if (! SportRegistry::isTeamSport($parsed->sport)) {
            return null;
        }

        $competition = $this->competitions->resolve($parsed->sport, $parsed->competition);
        if ($competition === null) {
            return null;
        }

        $inSlot = $candidates->filter(fn (SportEvent $match) => $match->competition_id === $competition->id
            && $match->starts_at->between($startsAt->subMinutes(self::SLOT_EARLY_MINUTES), $startsAt->addMinutes(self::SLOT_LATE_MINUTES)));

        return $inSlot->count() === 1 ? $inSlot->first() : null;
    }

    /**
     * Русские названия из телегида: запоминаем как написания команд (только
     * при полном совпадении обеих) и показываем у матча вместо латиницы.
     */
    private function learn(SportEvent $match, ParsedTitle $parsed, bool $swapped): void
    {
        [$home, $away] = $swapped ? [$parsed->away, $parsed->home] : [$parsed->home, $parsed->away];
        if ($home === null || $away === null) {
            return;
        }

        $strong = TeamMatch::score($home, $away, $match->homeTeam, $match->awayTeam, (string) $match->home, (string) $match->away)['score'] >= 2.0;

        if ($strong && $match->homeTeam !== null && $match->awayTeam !== null) {
            $this->teams->learn($match->homeTeam, $home);
            $this->teams->learn($match->awayTeam, $away);
        }

        // Название матча по-русски, если у него пока латиница; и искать его — по-русски тоже.
        if (! preg_match('/\p{Cyrillic}/u', (string) $match->home) && preg_match('/\p{Cyrillic}/u', $home)) {
            $title = "{$home} — {$away}";
            $match->update([
                'home' => $home,
                'away' => $away,
                'title' => $title,
                'search' => trim($match->search.' | '.mb_strtolower($title), ' |'),
            ]);
        }
    }
}
