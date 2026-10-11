<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SearchRequest;
use App\Sports\Catalog\Pages;
use Illuminate\Http\JsonResponse;

/**
 * Поиск по названиям: виды спорта, турниры, команды, сборные, игроки.
 */
class SearchController extends Controller
{
    public function __invoke(SearchRequest $request): JsonResponse
    {
        return response()->json(Pages::search($request->searchQuery()));
    }
}
