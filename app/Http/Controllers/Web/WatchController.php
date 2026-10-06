<?php

namespace App\Http\Controllers\Web;

use App\Guide\Data\Broadcast;
use App\Guide\Data\Event;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Просмотр: событие и одна из его трансляций, по умолчанию — первая
 * (основной показ, прямой эфир впереди).
 */
class WatchController extends Controller
{
    public function show(Event $event, ?int $broadcast = null): Response
    {
        $selected = $this->broadcast($event, $broadcast);

        return Inertia::render('watch', [
            'event' => EventResource::make($event)->resolve(),
            'broadcastId' => $selected->id,
            'streamUrl' => $selected->streamUrl,
            'playlistUrl' => route('events.playlist', ['event' => $event->id, 'broadcast' => $selected->id], absolute: false),
        ]);
    }

    /**
     * Плейлист из одного канала — открыть во внешнем плеере (VLC, IINA, Infuse).
     */
    public function playlist(Event $event, int $broadcast): HttpResponse
    {
        $selected = $this->broadcast($event, $broadcast);
        abort_if($selected->streamUrl === null, 404);

        $playlist = "#EXTM3U\n#EXTINF:-1,{$selected->channel}\n{$selected->streamUrl}\n";

        return response($playlist, 200, [
            'Content-Type' => 'audio/x-mpegurl; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="channel-'.$selected->channelNumber.'.m3u"',
        ]);
    }

    private function broadcast(Event $event, ?int $id): Broadcast
    {
        return collect($event->broadcasts)->first(fn (Broadcast $item) => $id === null || $item->id === $id) ?? abort(404);
    }
}
