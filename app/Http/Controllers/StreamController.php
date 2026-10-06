<?php

namespace App\Http\Controllers;

use App\Guide\Data\Broadcast;
use App\Guide\Data\Event;
use App\Http\Resources\BroadcastResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Откуда играть трансляцию: MPEG-TS канала Dispatcharr (в адресе — логин и
 * пароль Xtream, отдаются только после входа) или demo, если потока нет.
 */
class StreamController extends Controller
{
    public function show(Request $request, Event $event, int $broadcast): JsonResponse
    {
        $selected = collect($event->broadcasts)->first(fn (Broadcast $item) => $item->id === $broadcast) ?? abort(404);

        return response()->json([
            'broadcast' => BroadcastResource::make($selected)->resolve($request),
            'stream' => [
                'type' => $selected->streamUrl !== null ? 'mpegts' : 'demo',
                'url' => $selected->streamUrl,
            ],
        ]);
    }
}
