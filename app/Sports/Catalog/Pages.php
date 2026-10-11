<?php

namespace App\Sports\Catalog;

use App\Http\Resources\BroadcastResource;
use App\Http\Resources\Catalog\AthleteItem;
use App\Http\Resources\Catalog\CompetitionItem;
use App\Http\Resources\Catalog\EventDetail;
use App\Http\Resources\Catalog\EventItem;
use App\Http\Resources\Catalog\TeamItem;
use App\Sports\Broadcasts\EventBroadcasts;
use App\Sports\Models\Athlete;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Country;
use App\Sports\Models\Entry;
use App\Sports\Models\Event;
use App\Sports\Models\Name;
use App\Sports\Models\Sport;
use App\Sports\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Содержимое страниц каталога — общее для веб-клиента (Inertia) и API
 * ТВ-клиентов (/api/v1): одна выборка, один вид данных. Контроллеры
 * добавляют только своё — хлебные крошки, боковую панель, вкладку.
 */
final class Pages
{
    /** Ближайших событий каждого вида спорта на главной. */
    private const int PER_SPORT = 6;

    /** Трансляций сегодня на главной. */
    private const int HOME_BROADCASTS = 8;

    private const int SEARCH_LIMIT = 40;

    private const int PLAYERS_LIMIT = 120;

    /** Порядок групп турниров в разделе. */
    private const array SECTIONS = ['Лиги', 'Кубки', 'Турниры', 'Женские турниры', 'Молодёжные', 'Сборные'];

    /**
     * Связи турнира для CompetitionItem.
     *
     * @return array<int|string, mixed>
     */
    public static function competitionRelations(): array
    {
        return ['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag'];
    }

    /**
     * @return array{key: string, name: string, family: string, url: string, has_data: bool, wave: string}
     */
    public static function sport(Sport $sport): array
    {
        return ['key' => $sport->key, 'name' => $sport->name, 'family' => $sport->family, 'url' => Display::sportUrl($sport->key), 'has_data' => $sport->active, 'wave' => $sport->wave];
    }

    /**
     * Главная: трансляции сегодня и ближайшие события каждого вида спорта
     * с числом событий на неделю вперёд (сегодня, иначе — ближайший день с событиями).
     *
     * @return array<string, mixed>
     */
    public static function home(): array
    {
        $today = CarbonImmutable::now(config('sports.timezone'))->startOfDay();
        [$from] = EventFeed::window($today);
        [, $to] = EventFeed::window($today->addDays(6));
        $sports = Sport::query()->where('active', true)->orderBy('position')->get();

        $broadcasts = EventFeed::day($today, onTv: true)->get();

        return [
            'broadcast_groups' => EventGroups::byCompetition($broadcasts->take(self::HOME_BROADCASTS)->values()),
            'broadcast_count' => $broadcasts->count(),
            'date' => $today->toDateString(),
            'sports' => $sports->map(function (Sport $sport) use ($today, $from, $to) {
                $week = EventFeed::query()->setEagerLoads([])->whereDoesntHave('children')
                    ->whereHas('competition', fn (Builder $query) => $query->where('sport_id', $sport->id))
                    ->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
                    ->count();
                $day = EventFeed::nextDay($today->subDay(), $sport) ?? $today;
                $events = EventFeed::day($day, $sport)->limit(self::PER_SPORT)->get();

                return [
                    'key' => $sport->key,
                    'name' => $sport->name,
                    'url' => Display::sportUrl($sport->key),
                    'week' => $week,
                    'day' => $events->isEmpty() ? null : $day->toDateString(),
                    'groups' => EventGroups::byCompetition($events->sortBy(fn (Event $event) => $event->starts_at->timestamp ?? PHP_INT_MAX)->values()),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Расписание дня под заголовками турниров и ближайший день с событиями, если этот пуст.
     *
     * @return array{groups: list<array<string, mixed>>, next_date: string|null}
     */
    public static function day(CarbonImmutable $day, ?Sport $sport = null, ?Category $category = null, ?Competition $competition = null, bool $onTv = false): array
    {
        $events = EventFeed::day($day, $sport, $category, $competition, $onTv)->get();

        return [
            'groups' => EventGroups::byCompetition($events),
            'next_date' => $events->isEmpty() ? EventFeed::nextDay($day, $sport, $category, $onTv)?->toDateString() : null,
        ];
    }

    /**
     * Все разделы вида спорта с их турнирами (турнир — в каждом своём разделе).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function competitionSections(Sport $sport): array
    {
        $categories = Category::query()
            ->with(['sport', 'country.flag', 'names' => Display::names()])
            ->where('sport_id', $sport->id)
            ->orderBy('position')
            ->get()
            ->sortBy(fn (Category $category) => sprintf('%04d-%s', $category->position, mb_strtolower((string) Display::name($category))));
        $competitions = Competition::query()
            ->with([...self::competitionRelations(), 'categories'])
            ->where('sport_id', $sport->id)
            ->orderBy('position')
            ->get();

        return $categories->map(fn (Category $category) => [
            'key' => $category->key,
            'name' => (string) Display::name($category),
            'kind' => $category->kind,
            'url' => Display::categoryUrl($sport->key, $category),
            'flag' => Display::flag($category->country),
            'parent_key' => $category->parent_id !== null ? $categories->firstWhere('id', $category->parent_id)?->key : null,
            'competitions' => CompetitionItem::collection($competitions->filter(fn (Competition $competition) => $competition->categories->contains('id', $category->id))->values())->resolve(),
        ])->values()->all();
    }

    /**
     * Раздел (страна, регион, тур): шапка, турниры по группам — лиги и кубки,
     * женские, молодёжные, сборные, — подразделы и страна для списка сборных.
     *
     * @return array<string, mixed>
     */
    public static function category(Sport $sport, Category $category): array
    {
        $competitions = Competition::query()
            ->with(self::competitionRelations())
            ->whereHas('categories', fn ($query) => $query->whereKey($category->id))
            ->orderBy('position')
            ->orderBy('name')
            ->get();
        $children = Category::query()
            ->with(['sport', 'country.flag', 'names' => Display::names()])
            ->withCount('competitions')
            ->where('parent_id', $category->id)
            ->orderBy('position')
            ->get();

        $sections = $competitions->groupBy(fn (Competition $competition) => match (true) {
            $competition->participant_kind === 'national' => 'Сборные',
            $competition->gender === 'women' => 'Женские турниры',
            $competition->age_group !== null && $competition->age_group !== 'senior' => 'Молодёжные',
            $competition->kind === 'league' => 'Лиги',
            $competition->kind === 'cup' => 'Кубки',
            default => 'Турниры',
        })->sortBy(fn ($group, string $title) => array_search($title, self::SECTIONS, true));

        // Сборные этой страны; раздел-регион («Европа») не делает сборные европейскими.
        $hasNationalTeams = $category->country_id !== null
            && Team::query()->where('sport_id', $sport->id)->where('kind', 'national')->where('country_id', $category->country_id)->exists();

        return [
            'category' => [
                'key' => $category->key,
                'kind' => $category->kind,
                'name' => Display::name($category),
                'flag' => Display::flag($category->country),
                'url' => Display::categoryUrl($sport->key, $category),
                'national_teams_country' => $hasNationalTeams ? $category->country?->code : null,
            ],
            'sections' => $sections->map(fn ($group, string $title) => ['title' => $title, 'competitions' => CompetitionItem::collection($group->values())->resolve()])->values()->all(),
            'children' => $children->map(fn (Category $child) => [
                'key' => $child->key, 'name' => Display::name($child), 'url' => Display::categoryUrl($sport->key, $child), 'competitions_count' => $child->competitions_count,
            ])->all(),
        ];
    }

    /**
     * Сборные вида спорта: страны со сборными и варианты сборной выбранной
     * страны — старшая, женская, молодёжные, олимпийская.
     *
     * @return array<string, mixed>
     */
    public static function nationalTeams(Sport $sport, ?string $countryCode): array
    {
        $teams = Team::query()
            ->with(['sport', 'logo', 'names' => Display::names(), 'country.flag', 'country.names' => Display::names()])
            ->where('sport_id', $sport->id)
            ->where('kind', 'national')
            ->get();
        $country = $countryCode !== null ? Country::query()->with(['flag', 'names' => Display::names()])->where('code', $countryCode)->first() : null;

        $order = fn (Team $team) => sprintf('%d-%d-%s', $team->gender === 'women' ? 1 : 0, match ($team->squad) {
            'olympic' => 2, default => $team->age_group === 'senior' || $team->age_group === null ? 0 : 1
        }, $team->age_group ?? '');

        return [
            'countries' => $teams->whereNotNull('country_id')->groupBy('country_id')->map(function ($group) {
                $country = $group->first()->country;

                return ['code' => $country->code, 'name' => (string) Display::name($country), 'flag' => Display::flag($country), 'teams' => $group->count()];
            })->sortBy('name')->values()->all(),
            'country' => $country !== null ? ['code' => $country->code, 'name' => Display::name($country), 'flag' => Display::flag($country)] : null,
            'teams' => $country !== null ? TeamItem::collection($teams->where('country_id', $country->id)->sortBy($order)->values())->resolve() : [],
            // Сборные, у которых источник не указал страну, тоже доступны — отдельно.
            'without_country' => TeamItem::collection($teams->whereNull('country_id')->values())->resolve(),
        ];
    }

    /**
     * Спортсмены вида спорта, по имени на любом языке.
     *
     * @return array<mixed>
     */
    public static function players(Sport $sport, string $query): array
    {
        $normalized = Names::normalize($query);

        return AthleteItem::collection(Athlete::query()
            ->with(['names' => Display::names(), 'nationalities.country.flag', 'nationalities.country.names' => Display::names()])
            ->whereHas('sports', fn ($q) => $q->whereKey($sport->id))
            ->when($normalized !== '', fn ($q) => $q->whereIn('id', fn ($names) => $names->select('nameable_id')->from('catalog_names')->where('nameable_type', 'athlete')->where('normalized', 'like', "%{$normalized}%")))
            ->orderBy('name')
            ->limit(self::PLAYERS_LIMIT)
            ->get())->resolve();
    }

    /**
     * Поиск по всем названиям — английским, русским, псевдонимам. Одно и то же
     * название у разных сущностей — разные результаты с подписями.
     *
     * @return array{query: string, too_short: bool, results: array<string, array<mixed>>}
     */
    public static function search(string $query): array
    {
        $normalized = Names::normalize($query);
        $results = ['sports' => [], 'competitions' => [], 'teams' => [], 'national_teams' => [], 'players' => []];

        if (mb_strlen($normalized) >= 2) {
            $matches = Name::query()
                ->where(fn ($q) => $q->where('normalized', 'like', "{$normalized}%")->orWhere('normalized', 'like', "% {$normalized}%"))
                ->limit(600)
                ->get(['nameable_type', 'nameable_id'])
                ->groupBy('nameable_type')
                ->map(fn ($rows) => $rows->pluck('nameable_id')->unique()->values()->all());
            // И по собственному названию сущности — если отдельной записи о названии нет.
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
            foreach (['team' => Team::class, 'athlete' => Athlete::class, 'competition' => Competition::class] as $type => $model) {
                $own = $model::query()->where('name', 'like', $like)->limit(200)->pluck('id')->all();
                $matches->put($type, array_values(array_unique([...$matches->get($type, []), ...$own])));
            }

            $results['sports'] = Sport::query()->whereIn('id', $matches->get('sport', []))->orderBy('position')->get()
                ->map(fn (Sport $sport) => ['key' => $sport->key, 'name' => $sport->name, 'url' => Display::sportUrl($sport->key), 'has_data' => $sport->active])->all();
            $results['competitions'] = CompetitionItem::collection(
                Competition::query()->with(self::competitionRelations())
                    ->whereIn('id', $matches->get('competition', []))->orderBy('position')->limit(self::SEARCH_LIMIT)->get(),
            )->resolve();
            $teams = Team::query()->with(['sport', 'logo', 'names' => Display::names(), 'country.flag', 'country.names' => Display::names()])
                ->whereIn('id', $matches->get('team', []))->orderBy('name')->limit(self::SEARCH_LIMIT * 2)->get();
            $results['teams'] = TeamItem::collection($teams->where('kind', '!=', 'national')->take(self::SEARCH_LIMIT)->values())->resolve();
            $results['national_teams'] = TeamItem::collection($teams->where('kind', 'national')->take(self::SEARCH_LIMIT)->values())->resolve();
            $results['players'] = AthleteItem::collection(
                Athlete::query()->with(['names' => Display::names(), 'sports', 'nationalities.country.flag', 'nationalities.country.names' => Display::names()])
                    ->whereIn('id', $matches->get('athlete', []))->orderBy('name')->limit(self::SEARCH_LIMIT)->get(),
            )->resolve();
        }

        return [
            'query' => $query,
            'too_short' => $query !== '' && mb_strlen($normalized) < 2,
            'results' => $results,
        ];
    }

    /**
     * Турнир: шапка и ближайшие матчи порцией. Без таблиц, сеток и результатов.
     *
     * @return array<string, mixed>
     */
    public static function competition(Competition $competition, int $limit): array
    {
        $sport = $competition->sport;
        [$fixtures, $more] = Paging::slice(EventFeed::competition($competition, $limit + 1), $limit);

        return [
            'competition' => [
                'id' => $competition->id,
                'name' => Display::name($competition),
                'original_name' => Display::originalName($competition),
                'logo' => $competition->logo?->url,
                'url' => Display::competitionUrl($sport->key, $competition),
                'badges' => Display::competitionBadges($competition),
                'category' => [
                    'key' => $competition->primaryCategory->key,
                    'name' => Display::name($competition->primaryCategory),
                    'flag' => Display::flag($competition->primaryCategory->country),
                    'url' => Display::categoryUrl($sport->key, $competition->primaryCategory),
                ],
            ],
            'sport' => self::sport($sport),
            'fixtures' => EventItem::collection($fixtures)->resolve(),
            'limit' => $limit,
            'more' => $more,
        ];
    }

    /**
     * Команда или сборная: ближайшие матчи, турниры и другие сборные той же страны.
     *
     * @return array<string, mixed>
     */
    public static function team(Team $team, int $limit): array
    {
        $entries = Entry::query()->where('team_id', $team->id)->get()->map(fn (Entry $entry) => $entry->id)->values()->all();
        [$upcoming, $more] = Paging::slice(EventFeed::forEntries($entries, $limit + 1), $limit);
        $variants = $team->kind === 'national' && $team->country_id !== null
            ? Team::query()->with(['sport', 'logo', 'names' => Display::names()])->where('sport_id', $team->sport_id)->where('kind', 'national')->where('country_id', $team->country_id)->get()
            : collect();

        return [
            'team' => TeamItem::make($team)->resolve(),
            'sport' => self::sport($team->sport),
            'upcoming' => EventItem::collection($upcoming)->resolve(),
            'limit' => $limit,
            'more' => $more,
            'competitions' => self::competitionsOf($entries),
            'variants' => TeamItem::collection($variants)->resolve(),
        ];
    }

    /**
     * Спортсмен: ближайшие матчи — одиночные и в парах, эстафеты; турниры;
     * партнёры по парам. Возраст и биография не выдумываются.
     *
     * @return array<string, mixed>
     */
    public static function athlete(Athlete $athlete, int $limit): array
    {
        $entries = Entry::query()->with(['athletes.names' => Display::names(), 'athletes.nationalities.country.flag'])
            ->whereHas('members', fn ($query) => $query->where('athlete_id', $athlete->id))
            ->get();
        $ids = $entries->map(fn (Entry $entry) => $entry->id)->values()->all();
        [$upcoming, $more] = Paging::slice(EventFeed::forEntries($ids, $limit + 1), $limit);

        return [
            'athlete' => AthleteItem::make($athlete)->resolve() + ['gender' => $athlete->gender],
            'sport' => ($sport = $athlete->sports->first()) !== null ? self::sport($sport) : null,
            'upcoming' => EventItem::collection($upcoming)->resolve(),
            'limit' => $limit,
            'more' => $more,
            'competitions' => self::competitionsOf($ids),
            // Пары и эстафеты — заявки, где он один из участников; партнёры — свои профили.
            'partnerships' => $entries->where('kind', '!=', 'individual')->map(fn (Entry $entry) => [
                'kind' => $entry->kind,
                'partners' => $entry->athletes->where('id', '!=', $athlete->id)->map(fn (Athlete $partner) => AthleteItem::make($partner)->resolve())->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Событие: шапка, трансляции (без адресов потоков) и соседние события турнира.
     *
     * @return array<string, mixed>
     */
    public static function event(Event $event, EventBroadcasts $broadcasts): array
    {
        $parentName = $event->parent->name_local ?? $event->parent?->name;

        return [
            'event' => EventDetail::make($event)->resolve(),
            'broadcasts' => BroadcastResource::collection($broadcasts->for($event))->resolve(),
            'sport' => self::sport($event->competition->sport),
            'related' => EventItem::collection(EventFeed::related($event))->resolve(),
            'related_title' => $event->parent_id !== null ? 'Другие сессии: '.$parentName : 'Ближайшие матчи турнира',
        ];
    }

    /**
     * Турниры заявок — по всем их событиям, а не только по показанной порции.
     *
     * @param  array<int, int>  $entryIds
     * @return array<mixed>
     */
    private static function competitionsOf(array $entryIds): array
    {
        $ids = Event::query()->whereHas('entries', fn ($query) => $query->whereIn('entry_id', $entryIds))->distinct()->pluck('competition_id');

        /** @var Collection<int, Competition> $competitions */
        $competitions = Competition::query()->with(self::competitionRelations())->whereIn('id', $ids)->orderBy('position')->get();

        return CompetitionItem::collection($competitions)->resolve();
    }
}
