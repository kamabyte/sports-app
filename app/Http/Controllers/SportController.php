<?php

namespace App\Http\Controllers;

use App\Guide\Data\Sport;
use App\Guide\EventCatalog;
use App\Http\Resources\CompetitionResource;
use App\Http\Resources\SportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SportController extends Controller
{
    public function index(Request $request, EventCatalog $catalog): JsonResponse
    {
        $sports = $catalog->sports()->map(fn (Sport $sport) => [
            ...SportResource::make($sport)->resolve($request),
            'competitions' => CompetitionResource::collection($catalog->competitions($sport->key))->resolve($request),
        ]);

        return response()->json([
            'data' => $sports,
        ]);
    }
}
