<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CatalogDayRequest;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use App\Sports\Catalog\SportPanel;
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
        $contents = Pages::category($sport, $category);
        $country = $contents['category']['national_teams_country'];

        return Inertia::render('catalog/category', [
            'sport' => Pages::sport($sport),
            ...$contents,
            'crumbs' => $this->crumbs($sport, $category),
            // Сборные этой страны — отдельной ссылкой.
            'national_teams_url' => $country !== null ? Display::sportUrl($sport->key).'/national-teams?country='.$country : null,
            'filters' => ['date' => $day->toDateString(), 'tv' => $request->onTv()],
            ...Pages::day($day, $sport, $category, onTv: $request->onTv()),
            'panel' => SportPanel::for($sport),
        ]);
    }
}
