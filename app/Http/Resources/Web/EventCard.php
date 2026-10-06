<?php

namespace App\Http\Resources\Web;

use App\Guide\Data\Broadcast;
use App\Guide\Data\Event;
use App\Http\Resources\EventResource;
use Illuminate\Http\Request;

/**
 * Событие в списках веб-клиента: вместо трансляций — только названия
 * каналов для подписи «Матч Премьер +2» (без повторов).
 *
 * @mixin Event
 */
class EventCard extends EventResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $card = parent::toArray($request);
        unset($card['broadcasts']);

        return [
            ...$card,
            // Каналы, где идёт сам матч; повторы — не «где смотреть».
            'channels' => array_values(array_unique(array_map(
                fn (Broadcast $broadcast) => $broadcast->channel,
                array_filter($this->broadcasts, fn (Broadcast $broadcast) => ! $broadcast->replay),
            ))),
        ];
    }
}
