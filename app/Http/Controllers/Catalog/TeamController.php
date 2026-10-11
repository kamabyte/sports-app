<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PageRequest;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use App\Sports\Models\Team;
use Illuminate\Http\JsonResponse;

/**
 * Команда или сборная по id: ближайшие матчи, турниры, другие сборные страны.
 */
class TeamController extends Controller
{
    public function show(PageRequest $request, int $team): JsonResponse
    {
        $team = Team::query()->with(['sport', 'logo', 'names' => Display::names(), 'country.flag', 'country.names' => Display::names()])->find($team) ?? abort(404);

        return response()->json(Pages::team($team, $request->limit(30)));
    }
}
