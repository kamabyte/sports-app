<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CatalogDayRequest;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use App\Sports\Catalog\SportPanel;
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
        $category = $request->category($sport);
        $competition = $request->competition($sport);

        return Inertia::render('catalog/sport', [
            'sport' => Pages::sport($sport),
            'filters' => [
                'date' => $day->toDateString(),
                'category' => $category !== null ? ['key' => $category->key, 'name' => Display::name($category)] : null,
                'competition' => $competition !== null ? ['id' => $competition->id, 'name' => Display::name($competition)] : null,
                'tv' => $request->onTv(),
            ],
            ...Pages::day($day, $sport, $category, $competition, $request->onTv()),
            'panel' => SportPanel::for($sport),
        ]);
    }

    public function competitions(string $sport): Response
    {
        $sport = $this->sport($sport);

        return Inertia::render('catalog/competitions', [
            'sport' => Pages::sport($sport),
            'sections' => Pages::competitionSections($sport),
            'panel' => SportPanel::for($sport),
        ]);
    }

    public function nationalTeams(Request $request, string $sport): Response
    {
        $sport = $this->sport($sport);

        return Inertia::render('catalog/national-teams', [
            'sport' => Pages::sport($sport),
            ...Pages::nationalTeams($sport, $request->string('country')->toString() ?: null),
            'panel' => SportPanel::for($sport),
        ]);
    }

    public function players(Request $request, string $sport): Response
    {
        $sport = $this->sport($sport);
        $query = is_string($request->query('q')) ? trim($request->query('q')) : '';

        return Inertia::render('catalog/players', [
            'sport' => Pages::sport($sport),
            'query' => $query,
            'players' => Pages::players($sport, $query),
            'panel' => SportPanel::for($sport),
        ]);
    }
}
