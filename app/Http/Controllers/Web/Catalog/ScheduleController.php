<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CatalogDayRequest;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
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
        $sport = $request->sport();
        $category = $request->category($sport);
        $competition = $request->competition($sport);

        return Inertia::render('catalog/schedule', [
            'filters' => [
                'date' => $day->toDateString(),
                'sport' => $sport?->key,
                'category' => $category !== null ? ['key' => $category->key, 'name' => Display::name($category)] : null,
                'competition' => $competition !== null ? ['id' => $competition->id, 'name' => Display::name($competition)] : null,
                'tv' => $request->onTv(),
            ],
            'sports' => Sport::query()->where('active', true)->orderBy('position')->get()->map(fn (Sport $item) => ['key' => $item->key, 'name' => $item->name])->all(),
            ...Pages::day($day, $sport, $category, $competition, $request->onTv()),
        ]);
    }
}
