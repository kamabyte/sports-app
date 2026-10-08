<?php

namespace App\Http\Resources\Catalog;

use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use App\Sports\Models\Event;
use App\Sports\Models\EventEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Страница события: шапка как у строки расписания плюс место, участники
 * гонки или турнира, сессии этапа. Без счёта и протоколов — только расписание.
 *
 * @mixin Event
 */
class EventDetail extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return EventItem::make($this->resource)->resolve() + [
            'venue' => $this->venue,
            'ends_at' => $this->ends_at?->toIso8601String(),
            'start_list' => $this->startList(),
            'children' => EventItem::collection(EventFeed::query()->where('parent_id', $this->id)->orderBy('starts_at')->get())->resolve(),
            'siblings' => $this->parent_id === null ? [] : Event::query()->where('parent_id', $this->parent_id)->orderBy('starts_at')->get(['id', 'name', 'name_local', 'status', 'starts_at'])
                ->map(fn (Event $sibling) => ['id' => $sibling->id, 'name' => $sibling->name_local ?? $sibling->name, 'status' => $sibling->status, 'url' => Display::eventUrl($sibling->id), 'current' => $sibling->id === $this->id])->all(),
        ];
    }

    /**
     * Участники гонки, сессии или турнира (у очной встречи — стороны в шапке).
     *
     * @return list<array<string, mixed>>
     */
    private function startList(): array
    {
        if (in_array($this->kind, EventItem::HEAD_TO_HEAD, true)) {
            return [];
        }

        return array_values($this->entries
            ->filter(fn (EventEntry $row) => $row->entry !== null)
            ->map(fn (EventEntry $row) => ['participant' => EventItem::participant($row->entry), 'affiliation' => $row->affiliation])
            ->values()
            ->all());
    }
}
