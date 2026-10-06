<?php

namespace App\Http\Controllers\Web;

use App\Guide\Data\Event;
use App\Guide\Data\EventStatus;
use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\Web\EventCard;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const int RELATED_SIZE = 12;

    public function show(EventCatalog $catalog, Event $event): Response
    {
        // «Ещё в турнире» — ближайшее, что не закончилось.
        $related = $catalog->events(new EventQuery(
            competition: $event->competition->key,
            statuses: [EventStatus::Live, EventStatus::Upcoming],
        ))
            ->reject(fn (Event $item) => $item->id === $event->id)
            ->take(self::RELATED_SIZE)
            ->values();

        return Inertia::render('event', [
            'event' => EventResource::make($event)->resolve(),
            'related' => EventCard::collection($related)->resolve(),
        ]);
    }
}
