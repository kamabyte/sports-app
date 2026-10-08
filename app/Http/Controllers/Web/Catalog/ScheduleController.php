<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CatalogDayRequest;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use App\Sports\Catalog\EventGroups;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Sport;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Расписание всех видов спорта за день с фильтрами в адресе: вид спорта,
 * раздел и турнир. Раздел и турнир другого вида спорта не
 * применяются и не показываются — устаревший фильтр не прячет события.
 */
class ScheduleController extends Controller
{
    public function __invoke(CatalogDayRequest $request): Response
    {
        $day = $request->day();
        $sport = $request->sportKey() !== null ? Sport::query()->where('key', $request->sportKey())->first() : null;
        $category = $sport !== null && $request->categoryKey() !== null
            ? Category::query()->with(['names' => Display::names()])->where('sport_id', $sport->id)->where('key', $request->categoryKey())->first()
            : null;
        $competition = $sport !== null && $request->competitionId() !== null
            ? Competition::query()->with(['names' => Display::names()])->where('sport_id', $sport->id)->find($request->competitionId())
            : null;

        $events = EventFeed::day($day, $sport, $category, $competition)->get();

        return Inertia::render('catalog/schedule', [
            'filters' => [
                'date' => $day->toDateString(),
                'sport' => $sport?->key,
                'category' => $category !== null ? ['key' => $category->key, 'name' => Display::name($category)] : null,
                'competition' => $competition !== null ? ['id' => $competition->id, 'name' => Display::name($competition)] : null,
            ],
            'sports' => Sport::query()->where('active', true)->orderBy('position')->get()->map(fn (Sport $item) => ['key' => $item->key, 'name' => $item->name])->all(),
            'groups' => EventGroups::byCompetition($events),
            'next_date' => $events->isEmpty() ? EventFeed::nextDay($day, $sport, $category)?->toDateString() : null,
        ]);
    }
}
