<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\AthleteItem;
use App\Http\Resources\Catalog\CompetitionItem;
use App\Http\Resources\Catalog\EventItem;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use App\Sports\Catalog\Paging;
use App\Sports\Models\Athlete;
use App\Sports\Models\Competition;
use App\Sports\Models\Entry;
use App\Sports\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Спортсмен: флаг — только наблюдённый у источника; ближайшие матчи — одиночные и в
 * парах (с партнёрами), эстафеты; турниры. Возраст и биография не выдумываются.
 */
class AthleteController extends Controller
{
    public function show(Request $request, int $athlete, ?string $slug = null): Response|RedirectResponse
    {
        $athlete = Athlete::query()
            ->with(['names' => Display::names(), 'portrait', 'sports', 'nationalities.country.flag', 'nationalities.country.names' => Display::names()])
            ->find($athlete) ?? abort(404);
        if ($slug !== $athlete->slug) {
            return redirect(Display::athleteUrl($athlete));
        }

        $entries = Entry::query()->with(['athletes.names' => Display::names(), 'athletes.nationalities.country.flag'])
            ->whereHas('members', fn ($query) => $query->where('athlete_id', $athlete->id))
            ->get();
        $ids = $entries->map(fn (Entry $entry) => $entry->id)->values()->all();
        $limit = Paging::limit($request, 30);
        [$upcoming, $more] = Paging::slice(EventFeed::forEntries($ids, $limit + 1), $limit);
        // Турниры — по всем событиям, а не только по показанной порции.
        $competitionIds = Event::query()->whereHas('entries', fn ($query) => $query->whereIn('entry_id', $ids))->distinct()->pluck('competition_id');

        return Inertia::render('catalog/athlete', [
            'athlete' => AthleteItem::make($athlete)->resolve() + ['gender' => $athlete->gender],
            'sport' => ($sport = $athlete->sports->first()) !== null ? ['key' => $sport->key, 'name' => $sport->name, 'family' => $sport->family, 'url' => Display::sportUrl($sport->key)] : null,
            'upcoming' => EventItem::collection($upcoming)->resolve(),
            'limit' => $limit,
            'more' => $more,
            'competitions' => CompetitionItem::collection(
                Competition::query()->with(['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag'])->whereIn('id', $competitionIds)->orderBy('position')->get(),
            )->resolve(),
            // Пары и эстафеты — заявки, где он один из участников; партнёры — свои профили.
            'partnerships' => $entries->where('kind', '!=', 'individual')->map(fn (Entry $entry) => [
                'kind' => $entry->kind,
                'partners' => $entry->athletes->where('id', '!=', $athlete->id)->map(fn (Athlete $partner) => AthleteItem::make($partner)->resolve())->values()->all(),
            ])->values()->all(),
            'tab' => $request->string('tab')->toString() === 'competitions' ? 'competitions' : 'upcoming',
        ]);
    }
}
