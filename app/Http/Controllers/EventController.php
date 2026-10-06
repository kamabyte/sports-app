<?php

namespace App\Http\Controllers;

use App\Guide\Data\Event;
use App\Guide\EventCatalog;
use App\Http\Requests\EventIndexRequest;
use App\Http\Resources\EventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(EventIndexRequest $request, EventCatalog $catalog): JsonResponse
    {
        $events = $catalog->events($request->toQuery());

        return response()->json([
            'data' => EventResource::collection($events)->resolve($request),
        ]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        return response()->json([
            'data' => EventResource::make($event)->resolve($request),
        ]);
    }
}
