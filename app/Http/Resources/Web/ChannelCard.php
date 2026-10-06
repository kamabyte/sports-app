<?php

namespace App\Http\Resources\Web;

use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Канал для боковой панели и раздела «Каналы». on_air и now — только если их
 * посчитали в запросе (Channels::withMatches, Channels::all).
 *
 * @mixin Channel
 */
class ChannelCard extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'logo' => $this->logo,
            'category' => $this->category,
            'on_air' => $this->when(array_key_exists('on_air', $this->resource->getAttributes()), fn () => (bool) $this->resource->getAttribute('on_air')),
            // Матч из расписания, который идёт сейчас.
            'now' => $this->whenLoaded('airings', fn () => $this->current()),
        ];
    }

    /**
     * @return array{event_id: int, title: string, replay: bool}|null
     */
    private function current(): ?array
    {
        $airing = $this->airings->first();

        return $airing !== null
            ? ['event_id' => $airing->sportEvent->id, 'title' => $airing->sportEvent->title, 'replay' => $airing->replay]
            : null;
    }
}
