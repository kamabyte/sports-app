<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PageRequest;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use App\Sports\Models\Athlete;
use Illuminate\Http\JsonResponse;

/**
 * Спортсмен по id: ближайшие матчи (одиночные, в парах, эстафеты), турниры, партнёры.
 */
class PlayerController extends Controller
{
    public function show(PageRequest $request, int $athlete): JsonResponse
    {
        $athlete = Athlete::query()
            ->with(['names' => Display::names(), 'portrait', 'sports', 'nationalities.country.flag', 'nationalities.country.names' => Display::names()])
            ->find($athlete) ?? abort(404);

        return response()->json(Pages::athlete($athlete, $request->limit(30)));
    }
}
