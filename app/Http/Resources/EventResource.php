<?php

namespace App\Http\Resources;

use App\Guide\Data\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Событие для ТВ-клиентов: всегда со списком трансляций — клиенту не нужен
 * второй запрос, чтобы открыть канал.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $score = $this->score();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'sport' => SportResource::make($this->sport)->resolve($request),
            'competition' => CompetitionResource::make($this->competition)->resolve($request),
            'home' => $this->home ? TeamResource::make($this->home)->resolve($request) : null,
            'away' => $this->away ? TeamResource::make($this->away)->resolve($request) : null,
            'round' => $this->round,
            'venue' => $this->venue,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'status' => $this->status()->value,
            'live_label' => $this->liveLabel(),
            'progress' => round($this->progress(), 3),
            'score' => $score ? ['home' => $score->home, 'away' => $score->away] : null,
            'broadcasts' => BroadcastResource::collection($this->broadcasts)->resolve($request),
        ];
    }
}
