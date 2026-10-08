<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\EventItem;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
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

        $limit = Paging::limit($request);
        [$fixtures, $more] = Paging::slice(EventFeed::competition($competition, $limit + 1), $limit);

        return Inertia::render('catalog/competition', [
            'competition' => [
                'id' => $competition->id,
                'name' => Display::name($competition),
                'original_name' => Display::originalName($competition),
                'logo' => $competition->logo?->url,
                'url' => Display::competitionUrl($sport->key, $competition),
                'badges' => Display::competitionBadges($competition),
                'category' => ['name' => Display::name($competition->primaryCategory), 'flag' => Display::flag($competition->primaryCategory->country), 'url' => Display::categoryUrl($sport->key, $competition->primaryCategory)],
            ],
            'sport' => ['key' => $sport->key, 'name' => $sport->name, 'family' => $sport->family, 'url' => Display::sportUrl($sport->key), 'has_data' => $sport->active],
            'crumbs' => $this->crumbs($sport, $category, $competition),
            'fixtures' => EventItem::collection($fixtures)->resolve(),
            'limit' => $limit,
            'more' => $more,
            'panel' => SportPanel::for($sport),
        ]);
    }
}
