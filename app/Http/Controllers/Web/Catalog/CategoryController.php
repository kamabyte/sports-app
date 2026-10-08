<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CatalogDayRequest;
use App\Http\Resources\Catalog\CompetitionItem;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use App\Sports\Catalog\EventGroups;
use App\Sports\Catalog\SportPanel;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Раздел вида спорта (страна, регион, тур): его турниры по группам —
 * лиги и кубки, женские, молодёжные, сборные — и события выбранного дня.
 */
class CategoryController extends Controller
{
    use ResolvesCatalog;

    public function show(CatalogDayRequest $request, string $sport, string $category): Response
    {
        $sport = $this->sport($sport);
        $category = $this->category($sport, $category);
        $day = $request->day();

        $competitions = Competition::query()
            ->with(['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag'])
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
        })->sortBy(fn ($group, string $title) => array_search($title, ['Лиги', 'Кубки', 'Турниры', 'Женские турниры', 'Молодёжные', 'Сборные'], true));

        $events = EventFeed::day($day, $sport, $category)->get();

        return Inertia::render('catalog/category', [
            'sport' => ['key' => $sport->key, 'name' => $sport->name, 'family' => $sport->family, 'url' => Display::sportUrl($sport->key), 'has_data' => $sport->active],
            'category' => [
                'key' => $category->key,
                'kind' => $category->kind,
                'name' => Display::name($category),
                'flag' => Display::flag($category->country),
                'url' => Display::categoryUrl($sport->key, $category),
            ],
            'crumbs' => $this->crumbs($sport, $category),
            'sections' => $sections->map(fn ($group, string $title) => ['title' => $title, 'competitions' => CompetitionItem::collection($group->values())->resolve()])->values()->all(),
            'children' => $children->map(fn (Category $child) => [
                'key' => $child->key, 'name' => Display::name($child), 'url' => Display::categoryUrl($sport->key, $child), 'competitions_count' => $child->competitions_count,
            ])->all(),
            // Сборные этой страны — отдельной ссылкой; раздел-регион («Европа») не делает сборные европейскими.
            'national_teams_url' => $category->country_id !== null && Team::query()->where('sport_id', $sport->id)->where('kind', 'national')->where('country_id', $category->country_id)->exists()
                ? Display::sportUrl($sport->key).'/national-teams?country='.$category->country?->code
                : null,
            'filters' => ['date' => $day->toDateString()],
            'groups' => EventGroups::byCompetition($events),
            'next_date' => $events->isEmpty() ? EventFeed::nextDay($day, $sport, $category)?->toDateString() : null,
            'panel' => SportPanel::for($sport),
        ]);
    }
}
