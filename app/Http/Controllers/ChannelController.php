<?php

namespace App\Http\Controllers;

use App\Guide\Channels;
use App\Guide\Dispatcharr\XtreamClient;
use App\Guide\EventCatalog;
use App\Http\Resources\ChannelResource;
use App\Http\Resources\EventResource;
use App\Models\Airing;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request, Channels $channels): JsonResponse
    {
        return response()->json([
            'data' => ChannelResource::collection($channels->all())->resolve($request),
        ]);
    }

    /**
     * Канал: поток (адрес с логином Xtream — только после входа) и его матчи.
     */
    public function show(Request $request, Channels $channels, EventCatalog $catalog, XtreamClient $client, Channel $channel): JsonResponse
    {
        $schedule = $channels->schedule($channel)
            ->map(fn (Airing $airing) => [
                'starts_at' => $airing->starts_at->toIso8601String(),
                'ends_at' => $airing->ends_at->toIso8601String(),
                'replay' => $airing->replay,
                'event' => ($event = $catalog->find((string) $airing->sport_event_id)) !== null ? EventResource::make($event)->resolve($request) : null,
            ])
            ->filter(fn (array $item) => $item['event'] !== null)
            ->values();

        return response()->json([
            'data' => ChannelResource::make($channel)->resolve($request),
            'stream' => ['type' => 'mpegts', 'url' => $client->streamUrl($channel->stream_id)],
            'schedule' => $schedule,
        ]);
    }
}
