<?php

namespace App\Http\Controllers\Web;

use App\Guide\Channels;
use App\Guide\Dispatcharr\XtreamClient;
use App\Guide\EventCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\Web\ChannelCard;
use App\Http\Resources\Web\EventCard;
use App\Models\Airing;
use App\Models\Channel;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Каналы: все каналы источника и страница канала — эфир и его матчи.
 */
class ChannelController extends Controller
{
    public function index(Channels $channels): Response
    {
        return Inertia::render('channels', [
            'channels' => ChannelCard::collection($channels->all())->resolve(),
        ]);
    }

    public function show(Channels $channels, EventCatalog $catalog, XtreamClient $client, Channel $channel): Response
    {
        // Показы канала — к событиям каталога (с командами, эмблемами, счётом).
        $schedule = $channels->schedule($channel)
            ->map(fn (Airing $airing) => [
                'starts_at' => $airing->starts_at->toIso8601String(),
                'ends_at' => $airing->ends_at->toIso8601String(),
                'replay' => $airing->replay,
                'event' => ($event = $catalog->find((string) $airing->sport_event_id)) !== null ? EventCard::make($event)->resolve() : null,
            ])
            ->filter(fn (array $item) => $item['event'] !== null)
            ->values();

        return Inertia::render('channel', [
            'channel' => ChannelCard::make($channel)->resolve(),
            'streamUrl' => $client->streamUrl($channel->stream_id),
            'schedule' => $schedule,
        ]);
    }
}
