<?php

namespace App\Http\Controllers\Catalog;

use App\Guide\Data\Broadcast;
use App\Http\Controllers\Controller;
use App\Http\Resources\BroadcastResource;
use App\Sports\Broadcasts\EventBroadcasts;
use App\Sports\Catalog\EventFeed;
use App\Sports\Catalog\Pages;
use Illuminate\Http\JsonResponse;

/**
 * Событие каталога и поток одной из его трансляций. Адрес потока (в нём
 * логин Xtream) — только в stream, никогда в списках и в карточке события.
 */
class EventController extends Controller
{
    public function show(EventBroadcasts $broadcasts, int $catalogEvent): JsonResponse
    {
        $event = EventFeed::query()->find($catalogEvent) ?? abort(404);

        return response()->json(Pages::event($event, $broadcasts));
    }

    public function stream(EventBroadcasts $broadcasts, int $catalogEvent, int $broadcast): JsonResponse
    {
        $event = EventFeed::query()->find($catalogEvent) ?? abort(404);
        $selected = collect($broadcasts->for($event))->first(fn (Broadcast $item) => $item->id === $broadcast) ?? abort(404);

        return response()->json([
            'broadcast' => BroadcastResource::make($selected)->resolve(),
            'stream' => ['type' => 'mpegts', 'url' => $selected->streamUrl],
        ]);
    }
}
