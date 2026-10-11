<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use App\Sports\Catalog\Paging;
use App\Sports\Catalog\SportPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Турнир: шапка (эмблема, раздел, признаки) и ближайшие матчи по дням.
 * Без таблиц, сеток, стадий и результатов — каталог показывает расписание.
 */
class CompetitionController extends Controller
{
    use ResolvesCatalog;

    public function show(Request $request, string $sport, string $category, string $competition): Response|RedirectResponse
    {
        $sport = $this->sport($sport);
        $category = $this->category($sport, $category);
        $competition = $this->competition($category, $competition);

        // Адрес через второстепенный раздел ведёт на канонический.
        if ($competition->primary_category_id !== $category->id) {
            return redirect(Display::competitionUrl($sport->key, $competition).($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        }

        return Inertia::render('catalog/competition', Pages::competition($competition, Paging::limit($request)) + [
            'crumbs' => $this->crumbs($sport, $category, $competition),
            'panel' => SportPanel::for($sport),
        ]);
    }
}
