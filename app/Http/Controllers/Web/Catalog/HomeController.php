<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use App\Sports\Catalog\EventGroups;
use App\Sports\Models\Event;
use App\Sports\Models\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Главная: виды спорта с числом событий на неделю вперёд и ближайшие
 * события каждого вида спорта (сегодня, иначе — ближайший день с событиями).
 */
class HomeController extends Controller
{
    private const int PER_SPORT = 6;

    public function __invoke(): Response
    {
        $today = CarbonImmutable::now(config('sports.timezone'))->startOfDay();
        [$from] = EventFeed::window($today);
        [, $to] = EventFeed::window($today->addDays(6));
        $sports = Sport::query()->where('active', true)->orderBy('position')->get();

        $broadcasts = EventFeed::day($today, onTv: true)->get();

        return Inertia::render('catalog/home', [
            'broadcast_groups' => EventGroups::byCompetition($broadcasts->take(8)->values()),
            'broadcast_count' => $broadcasts->count(),
            'date' => $today->toDateString(),
            'sports' => $sports->map(function (Sport $sport) use ($today, $from, $to) {
                $week = EventFeed::query()->setEagerLoads([])->whereDoesntHave('children')
                    ->whereHas('competition', fn (Builder $query) => $query->where('sport_id', $sport->id))
                    ->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
                    ->count();
                $day = EventFeed::nextDay($today->subDay(), $sport) ?? $today;
                $events = EventFeed::day($day, $sport)->limit(self::PER_SPORT)->get();

                return [
                    'key' => $sport->key,
                    'name' => $sport->name,
                    'url' => Display::sportUrl($sport->key),
                    'week' => $week,
                    'day' => $events->isEmpty() ? null : $day->toDateString(),
                    'groups' => EventGroups::byCompetition($events->sortBy(fn (Event $event) => $event->starts_at->timestamp ?? PHP_INT_MAX)->values()),
                ];
            })->values()->all(),
        ]);
    }
}
