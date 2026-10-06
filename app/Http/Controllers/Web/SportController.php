<?php

namespace App\Http\Controllers\Web;

use App\Guide\Data\EventStatus;
use App\Guide\Data\Sport;
use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompetitionResource;
use App\Http\Resources\SportResource;
use App\Http\Resources\Web\EventCard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SportController extends Controller
{
    public function show(Request $request, EventCatalog $catalog, string $sport): Response
    {
        $selected = $catalog->sports()->first(fn (Sport $item) => $item->key === $sport) ?? abort(404);
        $competition = $request->string('competition')->toString() ?: null;

        $events = $catalog->events(new EventQuery(
            sport: $selected->key,
            competition: $competition,
            statuses: [EventStatus::Live, EventStatus::Upcoming],
        ));

        return Inertia::render('sport', [
            'sport' => SportResource::make($selected)->resolve(),
            'competitions' => CompetitionResource::collection($catalog->competitions($selected->key))->resolve(),
            'competition' => $competition,
            'events' => EventCard::collection($events)->resolve(),
        ]);
    }
}
