<?php

namespace App\Http\Resources;

use App\Guide\Data\Broadcast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Broadcast
 */
class BroadcastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'channel_number' => $this->channelNumber,
            'country' => $this->country,
            'language' => $this->language,
            'quality' => $this->quality,
            'provider' => $this->provider,
            'epg_title' => $this->epgTitle,
            'logo' => $this->logo,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'live' => $this->live,
            'replay' => $this->replay,
        ];
    }
}
