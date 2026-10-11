<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CatalogDayRequest;
use App\Http\Resources\Catalog\AthleteItem;
use App\Http\Resources\Catalog\CompetitionItem;
use App\Http\Resources\Catalog\TeamItem;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use App\Sports\Catalog\EventGroups;
use App\Sports\Catalog\Names;
use App\Sports\Catalog\SportPanel;
use App\Sports\Models\Athlete;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Country;
use App\Sports\Models\Sport;
use App\Sports\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Вид спорта: расписание дня под заголовками турниров, все разделы и
 * турниры, сборные по странам, игроки.
 */
class SportController extends Controller
{
    use ResolvesCatalog;

    public function show(CatalogDayRequest $request, string $sport): Response
    {
        $sport = $this->sport($sport);
        $day = $request->day();

        // Фильтр раздела/турнира другого вида спорта не применяется — его просто нет.
        $category = $request->categoryKey() !== null
            ? Category::query()->with(['names' => Display::names()])->where('sport_id', $sport->id)->where('key', $request->categoryKey())->first()
            : null;
        $competition = $request->competitionId() !== null
            ? Competition::query()->with(['names' => Display::names()])->where('sport_id', $sport->id)->find($request->competitionId())
            : null;

        $events = EventFeed::day($day, $sport, $category, $competition, $request->onTv())->get();

        return Inertia::render('catalog/sport', [
            'sport' => $this->sportProps($sport),
            'filters' => [
                'date' => $day->toDateString(),
                'category' => $category !== null ? ['key' => $category->key, 'name' => Display::name($category)] : null,
                'competition' => $competition !== null ? ['id' => $competition->id, 'name' => Display::name($competition)] : null,
                'tv' => $request->onTv(),
            ],
            'groups' => EventGroups::byCompetition($events),
            'next_date' => $events->isEmpty() ? EventFeed::nextDay($day, $sport, $category, $request->onTv())?->toDateString() : null,
            'panel' => SportPanel::for($sport),
        ]);
    }

    public function competitions(string $sport): Response
    {
        $sport = $this->sport($sport);
        $categories = Category::query()
            ->with(['sport', 'country.flag', 'names' => Display::names()])
            ->where('sport_id', $sport->id)
            ->orderBy('position')
            ->get()
            ->sortBy(fn (Category $category) => sprintf('%04d-%s', $category->position, mb_strtolower((string) Display::name($category))));
        $competitions = Competition::query()
            ->with(['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag', 'categories'])
            ->where('sport_id', $sport->id)
            ->orderBy('position')
            ->get();

        return Inertia::render('catalog/competitions', [
            'sport' => $this->sportProps($sport),
            'sections' => $categories->map(fn (Category $category) => [
                'key' => $category->key,
                'name' => (string) Display::name($category),
                'kind' => $category->kind,
                'url' => Display::categoryUrl($sport->key, $category),
                'flag' => Display::flag($category->country),
                'parent_key' => $category->parent_id !== null ? $categories->firstWhere('id', $category->parent_id)?->key : null,
                'competitions' => CompetitionItem::collection($competitions->filter(fn (Competition $competition) => $competition->categories->contains('id', $category->id))->values())->resolve(),
            ])->values()->all(),
            'panel' => SportPanel::for($sport),
        ]);
    }

    public function nationalTeams(Request $request, string $sport): Response
    {
        $sport = $this->sport($sport);
        $teams = Team::query()
            ->with(['sport', 'logo', 'names' => Display::names(), 'country.flag', 'country.names' => Display::names()])
            ->where('sport_id', $sport->id)
            ->where('kind', 'national')
            ->get();
        $selected = $request->string('country')->toString() ?: null;
        $country = $selected !== null ? Country::query()->with(['flag', 'names' => Display::names()])->where('code', $selected)->first() : null;

        // Варианты сборной одной страны — старшая, женская, молодёжные, олимпийская.
        $order = fn (Team $team) => sprintf('%d-%d-%s', $team->gender === 'women' ? 1 : 0, match ($team->squad) {
            'olympic' => 2, default => $team->age_group === 'senior' || $team->age_group === null ? 0 : 1
        }, $team->age_group ?? '');

        return Inertia::render('catalog/national-teams', [
            'sport' => $this->sportProps($sport),
            'countries' => $teams->whereNotNull('country_id')->groupBy('country_id')->map(function ($group) {
                $country = $group->first()->country;

                return ['code' => $country->code, 'name' => (string) Display::name($country), 'flag' => Display::flag($country), 'teams' => $group->count()];
            })->sortBy('name')->values()->all(),
            'country' => $country !== null ? ['code' => $country->code, 'name' => Display::name($country), 'flag' => Display::flag($country)] : null,
            'teams' => $country !== null ? TeamItem::collection($teams->where('country_id', $country->id)->sortBy($order)->values())->resolve() : [],
            // Сборные, у которых источник не указал страну, тоже доступны — отдельно.
            'without_country' => TeamItem::collection($teams->whereNull('country_id')->values())->resolve(),
            'panel' => SportPanel::for($sport),
        ]);
    }

    public function players(Request $request, string $sport): Response
    {
        $sport = $this->sport($sport);
        $query = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $normalized = Names::normalize($query);

        $athletes = Athlete::query()
            ->with(['names' => Display::names(), 'nationalities.country.flag', 'nationalities.country.names' => Display::names()])
            ->whereHas('sports', fn ($q) => $q->whereKey($sport->id))
            ->when($normalized !== '', fn ($q) => $q->whereIn('id', fn ($names) => $names->select('nameable_id')->from('catalog_names')->where('nameable_type', 'athlete')->where('normalized', 'like', "%{$normalized}%")))
            ->orderBy('name')
            ->limit(120)
            ->get();

        return Inertia::render('catalog/players', [
            'sport' => $this->sportProps($sport),
            'query' => $query,
            'players' => AthleteItem::collection($athletes)->resolve(),
            'panel' => SportPanel::for($sport),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sportProps(Sport $sport): array
    {
        return ['key' => $sport->key, 'name' => $sport->name, 'family' => $sport->family, 'url' => Display::sportUrl($sport->key), 'has_data' => $sport->active, 'wave' => $sport->wave];
    }
}
