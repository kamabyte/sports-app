<?php

namespace App\Http\Controllers\Web;

use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ScheduleRequest;
use App\Http\Resources\Web\EventCard;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Программа на день — как телегид, только из событий, а не каналов.
 */
class ScheduleController extends Controller
{
    private const int DAYS_BEFORE = 1;

    private const int DAYS_AFTER = 6;

    public function __invoke(ScheduleRequest $request, EventCatalog $catalog): Response
    {
        $day = $request->day();
        $today = CarbonImmutable::now(config('sports.timezone'))->startOfDay();

        $events = $catalog->events(EventQuery::forDay($day, $request->sport()));

        $days = collect(range(-self::DAYS_BEFORE, self::DAYS_AFTER))
            ->map(fn (int $offset) => $today->addDays($offset)->toDateString());

        return Inertia::render('schedule', [
            'date' => $day->toDateString(),
            'days' => $days,
            'sport' => $request->sport(),
            'events' => EventCard::collection($events)->resolve(),
        ]);
    }
}
