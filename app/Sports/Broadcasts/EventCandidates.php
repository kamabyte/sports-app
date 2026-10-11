<?php

namespace App\Sports\Broadcasts;

use App\Guide\TeamCategory;
use App\Sports\Models\Athlete;
use App\Sports\Models\Event;
use App\Sports\Models\EventEntry;
use App\Sports\Models\Name;
use App\Sports\Models\Team;
use Carbon\CarbonImmutable;

/**
 * События каталога, которые могут быть в телегиде: с точным временем, не
 * отменённые, не этап-«контейнер» (Гран-при целиком, турнир). Стороны — со
 * всеми написаниями: оригинал, переводы, сокращения.
 */
final class EventCandidates
{
    /** Повтор — в пределах двух недель после события. */
    public const int REPLAY_WINDOW_DAYS = 14;

    private const array SKIPPED_STATUSES = ['cancelled', 'postponed'];

    /** У этих событий свои сессии и матчи: эфир показывает их, а не контейнер. */
    private const array CONTAINERS = ['meeting', 'tournament'];

    /**
     * @return array<string, list<Candidate>> по виду спорта каталога
     */
    public function load(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = Event::query()
            ->with(['competition.sport', 'entries.entry.team.names', 'entries.entry.athletes.names'])
            ->whereNotNull('starts_at')
            ->where('time_precision', 'exact')
            ->where('publication_state', 'published')
            ->whereNotIn('status', self::SKIPPED_STATUSES)
            ->whereNotIn('kind', self::CONTAINERS)
            ->where('starts_at', '>=', $from->subDays(self::REPLAY_WINDOW_DAYS))
            ->where('starts_at', '<=', $to)
            ->get();

        $candidates = [];
        foreach ($events as $event) {
            $candidates[$event->competition->sport->key][] = $this->candidate($event);
        }

        return $candidates;
    }

    private function candidate(Event $event): Candidate
    {
        $sides = $event->entries->whereNotNull('side')->keyBy('side');
        $home = $sides->has(1) ? $this->names($sides[1]) : [];
        $away = $sides->has(2) ? $this->names($sides[2]) : [];
        $teams = $sides->map(fn (EventEntry $side) => $side->entry?->team)->filter()->values();

        return new Candidate(
            eventId: $event->id,
            competitionId: $event->competition_id,
            startsAt: CarbonImmutable::instance($event->starts_at),
            home: $home,
            away: $away,
            category: $this->category($event, $teams->all(), $home, $away),
            individual: $event->competition->participant_kind === 'individual',
        );
    }

    /**
     * Все написания стороны. Пара — фамилии через « / » на каждом языке.
     *
     * @return list<string>
     */
    private function names(EventEntry $side): array
    {
        $entry = $side->entry;
        if ($entry === null) {
            // Участник без карточки: подпись источника («Brighton»), если это не «Победитель матча 5».
            return $side->label !== null && $side->entry_status === 'unlisted' ? [$side->label] : [];
        }

        if ($entry->team !== null) {
            return $this->spellings($entry->team);
        }

        $athletes = $entry->athletes;
        if ($athletes->count() === 1) {
            return $this->spellings($athletes->first());
        }

        // Пара: «Granollers M. / Zeballos H.» и «Гранольерс М. / Себальос О.».
        $byLocale = [];
        foreach ($athletes as $athlete) {
            $byLocale['original'][] = $athlete->name;
            foreach ($athlete->names as $name) {
                $byLocale[$name->locale][] = $name->name;
            }
        }

        return array_values(array_unique(array_map(
            fn (array $names) => implode(' / ', $names),
            array_filter($byLocale, fn (array $names) => count($names) === $athletes->count()),
        )));
    }

    /**
     * @return list<string>
     */
    private function spellings(Team|Athlete $participant): array
    {
        $names = [$participant->name, $participant->short_name, ...$participant->names->map(fn (Name $name) => $name->name)];

        return array_values(array_unique(array_filter($names, fn (?string $name) => $name !== null && $name !== '')));
    }

    /**
     * Категория для сравнения с телегидом: «u21», «w», «u19w» или ''. Из
     * названий сторон и турнира («Superleague Women») и признаков команд;
     * у гонки — из турнира и сессии («Sprint - Women»).
     *
     * @param  array<int, Team>  $teams
     * @param  list<string>  $home
     * @param  list<string>  $away
     */
    private function category(Event $event, array $teams, array $home, array $away): string
    {
        $competition = $event->competition;
        $text = $home === [] && $away === []
            ? "{$competition->name} {$event->name}"
            : ($home[0] ?? '').' '.($away[0] ?? '')." {$competition->name}";
        $category = TeamCategory::of($text);

        $age = preg_match('/^u\d+/', $category, $match) ? $match[0] : '';
        $women = str_ends_with($category, 'w') || $competition->gender === 'women';
        foreach ($teams as $team) {
            if ($team->age_group !== null && preg_match('/^u\d+$/', $team->age_group)) {
                $age = $team->age_group;
            }
            $women = $women || $team->gender === 'women';
        }

        return $age.($women ? 'w' : '');
    }
}
