<?php

namespace App\Http\Resources;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Канал для ТВ-клиентов: номер, название, логотип, категория источника и
 * матч, который идёт сейчас (если его считали — Channels::all).
 *
 * @mixin Channel
 */
class ChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $airing = $this->relationLoaded('airings') ? $this->airings->first() : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'logo' => $this->logo,
            'category' => $this->category,
            'now' => $this->when($this->relationLoaded('airings'), fn () => $airing !== null
                ? ['event_id' => $airing->sport_event_id, 'title' => $airing->sportEvent->title, 'replay' => $airing->replay]
                : null),
        ];
    }
}
