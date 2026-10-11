<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Guide\Data\Broadcast;
use App\Http\Controllers\Controller;
use App\Http\Resources\BroadcastResource;
use App\Http\Resources\Catalog\EventDetail;
use App\Sports\Broadcasts\EventBroadcasts;
use App\Sports\Catalog\EventFeed;
use App\Sports\Models\Event;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Просмотр события каталога: одна из его трансляций, по умолчанию — первая
 * (показ во время события, прямой эфир впереди). Адрес потока — только здесь.
 */
class WatchController extends Controller
{
    public function show(EventBroadcasts $broadcasts, int $catalogEvent, ?int $broadcast = null): Response
    {
        $event = EventFeed::query()->find($catalogEvent) ?? abort(404);
        $list = $broadcasts->for($event);
        $selected = $this->broadcast($list, $broadcast);

        return Inertia::render('catalog/watch', [
            'event' => EventDetail::make($event)->resolve(),
            'broadcasts' => BroadcastResource::collection($list)->resolve(),
            'broadcastId' => $selected->id,
            'streamUrl' => $selected->streamUrl,
            'playlistUrl' => route('catalog.playlist', ['catalogEvent' => $event->id, 'broadcast' => $selected->id], absolute: false),
        ]);
    }

    /**
     * Плейлист из одного канала — открыть во внешнем плеере (VLC, IINA, Infuse).
     */
    public function playlist(EventBroadcasts $broadcasts, int $catalogEvent, int $broadcast): HttpResponse
    {
        $event = Event::query()->find($catalogEvent) ?? abort(404);
        $selected = $this->broadcast($broadcasts->for($event), $broadcast);
        abort_if($selected->streamUrl === null, 404);

        $playlist = "#EXTM3U\n#EXTINF:-1,{$selected->channel}\n{$selected->streamUrl}\n";

        return response($playlist, 200, [
            'Content-Type' => 'audio/x-mpegurl; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="channel-'.$selected->channelNumber.'.m3u"',
        ]);
    }

    /**
     * @param  list<Broadcast>  $broadcasts
     */
    private function broadcast(array $broadcasts, ?int $id): Broadcast
    {
        return collect($broadcasts)->first(fn (Broadcast $item) => $id === null || $item->id === $id) ?? abort(404);
    }
}
