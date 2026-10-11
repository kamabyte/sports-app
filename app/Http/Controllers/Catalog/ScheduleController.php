<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CatalogDayRequest;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use Illuminate\Http\JsonResponse;

/**
 * Расписание дня: все виды спорта или один, раздел, турнир, «только по ТВ».
 * Фильтры, которые не применились (раздел другого вида спорта), в ответе — null.
 */
class ScheduleController extends Controller
{
    public function __invoke(CatalogDayRequest $request): JsonResponse
    {
        $day = $request->day();
        $sport = $request->sport();
        $category = $request->category($sport);
        $competition = $request->competition($sport);

        return response()->json([
            'filters' => [
                'date' => $day->toDateString(),
                'sport' => $sport?->key,
                'category' => $category !== null ? ['key' => $category->key, 'name' => Display::name($category)] : null,
                'competition' => $competition !== null ? ['id' => $competition->id, 'name' => Display::name($competition)] : null,
                'tv' => $request->onTv(),
            ],
            ...Pages::day($day, $sport, $category, $competition, $request->onTv()),
        ]);
    }
}
