<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Catalog\ResolvesCatalog;
use App\Http\Requests\Catalog\SearchRequest;
use App\Sports\Catalog\Pages;
use App\Sports\Catalog\SportPanel;
use App\Sports\Models\Sport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Навигация вида спорта: разделы и популярные турниры, все турниры по
 * разделам, раздел, сборные по странам, игроки. События — в /schedule.
 */
class SportController extends Controller
{
    use ResolvesCatalog {
        category as findCategory;
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Sport::query()->orderBy('position')->get()->map(fn (Sport $sport) => Pages::sport($sport))->all(),
        ]);
    }

    public function show(string $sport): JsonResponse
    {
        $sport = $this->sport($sport);

        return response()->json(['sport' => Pages::sport($sport), ...SportPanel::for($sport)]);
    }

    public function competitions(string $sport): JsonResponse
    {
        $sport = $this->sport($sport);

        return response()->json(['sport' => Pages::sport($sport), 'sections' => Pages::competitionSections($sport)]);
    }

    public function category(string $sport, string $category): JsonResponse
    {
        $sport = $this->sport($sport);

        return response()->json(['sport' => Pages::sport($sport), ...Pages::category($sport, $this->findCategory($sport, $category))]);
    }

    public function nationalTeams(Request $request, string $sport): JsonResponse
    {
        $sport = $this->sport($sport);

        return response()->json(['sport' => Pages::sport($sport), ...Pages::nationalTeams($sport, $request->string('country')->toString() ?: null)]);
    }

    public function players(SearchRequest $request, string $sport): JsonResponse
    {
        $sport = $this->sport($sport);

        return response()->json([
            'sport' => Pages::sport($sport),
            'query' => $request->searchQuery(),
            'players' => Pages::players($sport, $request->searchQuery()),
        ]);
    }
}
