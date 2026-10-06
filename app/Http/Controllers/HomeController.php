<?php

namespace App\Http\Controllers;

use App\Guide\EventCatalog;
use App\Guide\HomeShelves;
use App\Http\Resources\EventResource;
use App\Http\Resources\SportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Главная ТВ-клиента — те же полки, что у Web\HomeController.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, EventCatalog $catalog): JsonResponse
    {
        $shelves = HomeShelves::build($catalog);

        $sports = $shelves->sports->map(fn (array $shelf) => [
            'sport' => SportResource::make($shelf['sport'])->resolve($request),
            'events' => EventResource::collection($shelf['events'])->resolve($request),
        ]);

        return response()->json([
            'featured' => EventResource::collection($shelves->featured)->resolve($request),
            'live' => EventResource::collection($shelves->live)->resolve($request),
            'starting_soon' => EventResource::collection($shelves->startingSoon)->resolve($request),
            'sports' => $sports,
        ]);
    }
}
