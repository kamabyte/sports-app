<?php

namespace App\Http\Controllers\Web;

use App\Guide\EventCatalog;
use App\Guide\HomeShelves;
use App\Http\Controllers\Controller;
use App\Http\Resources\SportResource;
use App\Http\Resources\Web\EventCard;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(EventCatalog $catalog): Response
    {
        $shelves = HomeShelves::build($catalog);

        $sports = $shelves->sports->map(fn (array $shelf) => [
            'sport' => SportResource::make($shelf['sport'])->resolve(),
            'events' => EventCard::collection($shelf['events'])->resolve(),
        ]);

        return Inertia::render('home', [
            'featured' => EventCard::collection($shelves->featured)->resolve(),
            'live' => EventCard::collection($shelves->live)->resolve(),
            'startingSoon' => EventCard::collection($shelves->startingSoon)->resolve(),
            'sportShelves' => $sports,
        ]);
    }
}
