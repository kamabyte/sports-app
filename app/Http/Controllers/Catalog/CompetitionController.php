<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\PageRequest;
use App\Sports\Catalog\Pages;
use App\Sports\Models\Competition;
use Illuminate\Http\JsonResponse;

/**
 * Турнир по id: шапка и ближайшие матчи порцией (limit, more).
 */
class CompetitionController extends Controller
{
    public function show(PageRequest $request, int $competition): JsonResponse
    {
        $competition = Competition::query()->with(Pages::competitionRelations())->find($competition) ?? abort(404);

        return response()->json(Pages::competition($competition, $request->limit()));
    }
}
