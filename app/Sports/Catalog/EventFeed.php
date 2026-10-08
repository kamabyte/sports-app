<?php

namespace App\Sports\Catalog;

use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Event;
use App\Sports\Models\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Выборки событий для страниц — только предстоящие (и идущие сейчас):
 * каталог показывает расписание, а не результаты и не историю. Перенесённые
 * и отменённые видны с пометкой. Везде одни и те же связи — строки списков
 * выглядят одинаково на любой странице.
 *
 * День — по часовому поясу зрителя. Событие с точным временем попадает в
 * день по началу; с известной только датой — по дате; многодневное (турнир
 * по гольфу) — в каждый свой день.
 */
final class EventFeed
{
    /** Прошедшее не показывается: завершённые и прерванные события. */
    private const array HIDDEN = ['finished', 'abandoned'];

    /**
     * Связи строки события в списке.
     *
     * @return array<int|string, mixed>
     */
    public static function relations(): array
    {
        $names = Display::names();

        return [
            'competition.sport',
            'competition.names' => $names,
            'competition.logo',
            'competition.primaryCategory.names' => $names,
            'competition.primaryCategory.country.flag',
            'parent',
            'entries.entry.team.logo',
            'entries.entry.team.names' => $names,
            'entries.entry.athletes.names' => $names,
            'entries.entry.athletes.nationalities.country.flag',
        ];
    }

    /**
     * Предстоящие и идущие события: не завершены и не в прошлом.
     *
     * @return Builder<Event>
     */
    public static function query(): Builder
    {
        $today = CarbonImmutable::now(config('sports.timezone'))->startOfDay();

        return Event::query()
            ->with(self::relations())
            ->withCount('entries')
            ->where('publication_state', 'published')
            ->whereNotIn('status', self::HIDDEN)
            ->where(fn (Builder $query) => $query
                ->whereIn('status', ['live', 'interrupted'])
                ->orWhere('starts_at', '>=', $today->utc())
                ->orWhere(fn (Builder $dated) => $dated->whereNull('starts_at')->whereDate('date', '>=', $today->toDateString()))
                ->orWhere('ends_at', '>=', $today->utc()));
    }

    /**
     * События дня. Родительские события (этап Формулы-1, многодневка) в
     * списках не повторяются: показываются их сессии и этапы.
     *
     * @return Builder<Event>
     */
    public static function day(CarbonImmutable $day, ?Sport $sport = null, ?Category $category = null, ?Competition $competition = null): Builder
    {
        [$from, $to] = self::window($day);

        return self::query()
            ->whereDoesntHave('children')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $exact) => $exact->where('time_precision', 'exact')->where('starts_at', '>=', $from)->where('starts_at', '<', $to))
                ->orWhere(fn (Builder $dated) => $dated->where('time_precision', '!=', 'exact')->whereDate('date', $day->toDateString()))
                ->orWhere(fn (Builder $long) => $long->whereNotNull('ends_at')->where('starts_at', '<', $to)->where('ends_at', '>=', $from)))
            ->when($sport, fn (Builder $query) => $query->whereHas('competition', fn (Builder $c) => $c->where('sport_id', $sport->id)))
            ->when($category, fn (Builder $query) => $query->whereHas('competition.categories', fn (Builder $c) => $c->whereKey($category->id)))
            ->when($competition, fn (Builder $query) => $query->where('competition_id', $competition->id))
            ->orderByRaw('starts_at is null, starts_at')
            ->orderBy('id');
    }

    /** Ближайший день после выбранного, в котором есть события — для кнопки «Ближайшие». */
    public static function nextDay(CarbonImmutable $day, ?Sport $sport = null, ?Category $category = null): ?CarbonImmutable
    {
        [, $to] = self::window($day);
        $next = self::query()
            ->setEagerLoads([])
            ->whereDoesntHave('children')
            ->where('starts_at', '>=', $to)
            ->when($sport, fn (Builder $query) => $query->whereHas('competition', fn (Builder $c) => $c->where('sport_id', $sport->id)))
            ->when($category, fn (Builder $query) => $query->whereHas('competition.categories', fn (Builder $c) => $c->whereKey($category->id)))
            ->orderBy('starts_at')
            ->value('starts_at');

        return $next === null ? null : CarbonImmutable::parse($next, 'UTC')->setTimezone(config('sports.timezone'))->startOfDay();
    }

    /**
     * Расписание турнира: ближайшие события по порядку.
     *
     * @return Collection<int, Event>
     */
    public static function competition(Competition $competition, int $limit): Collection
    {
        return self::query()
            ->where('competition_id', $competition->id)
            ->whereDoesntHave('children')
            ->orderByRaw('starts_at is null, starts_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Ближайшие события заявок (команды или спортсмена, включая его пары).
     *
     * @param  array<int, int>  $entryIds
     * @return Collection<int, Event>
     */
    public static function forEntries(array $entryIds, int $limit): Collection
    {
        return self::query()
            ->whereHas('entries', fn (Builder $query) => $query->whereIn('entry_id', $entryIds))
            ->orderByRaw('starts_at is null, starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Ещё события турнира: сессии того же этапа или ближайшие матчи турнира.
     *
     * @return Collection<int, Event>
     */
    public static function related(Event $event, int $limit = 12): Collection
    {
        return self::query()
            ->where('competition_id', $event->competition_id)
            ->whereKeyNot($event->id)
            ->whereDoesntHave('children')
            ->when($event->parent_id !== null, fn (Builder $query) => $query->where('parent_id', $event->parent_id))
            ->orderByRaw('starts_at is null, starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Начало и конец дня зрителя в UTC.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(CarbonImmutable $day): array
    {
        $start = $day->setTimezone(config('sports.timezone'))->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }
}
