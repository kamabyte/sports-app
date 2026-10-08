<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\CompetitionItem;
use App\Http\Resources\Catalog\EventItem;
use App\Http\Resources\Catalog\TeamItem;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use App\Sports\Catalog\Paging;
use App\Sports\Models\Competition;
use App\Sports\Models\Entry;
use App\Sports\Models\Event;
use App\Sports\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Команда или сборная: признаки (клуб/сборная, пол, возраст, олимпийская),
 * ближайшие матчи и турниры. У сборной — другие сборные той же
 * страны: старшая, женская, молодёжные — разные команды.
 */
class TeamController extends Controller
{
    public function show(Request $request, int $team, ?string $slug = null): Response|RedirectResponse
    {
        $team = Team::query()->with(['sport', 'logo', 'names' => Display::names(), 'country.flag', 'country.names' => Display::names()])->find($team) ?? abort(404);
        if ($slug !== $team->slug) {
            return redirect(Display::teamUrl($team));
        }

        $entries = Entry::query()->where('team_id', $team->id)->get()->map(fn (Entry $entry) => $entry->id)->values()->all();
        $limit = Paging::limit($request, 30);
        [$upcoming, $more] = Paging::slice(EventFeed::forEntries($entries, $limit + 1), $limit);
        // Турниры — по всем событиям, а не только по показанной порции.
        $competitionIds = Event::query()->whereHas('entries', fn ($query) => $query->whereIn('entry_id', $entries))->distinct()->pluck('competition_id');

        $variants = $team->kind === 'national' && $team->country_id !== null
            ? Team::query()->with(['sport', 'logo', 'names' => Display::names()])->where('sport_id', $team->sport_id)->where('kind', 'national')->where('country_id', $team->country_id)->get()
            : collect();

        return Inertia::render('catalog/team', [
            'team' => TeamItem::make($team)->resolve(),
            'sport' => ['key' => $team->sport->key, 'name' => $team->sport->name, 'family' => $team->sport->family, 'url' => Display::sportUrl($team->sport->key)],
            'national_teams_url' => $team->kind === 'national' && $team->country !== null
                ? Display::sportUrl($team->sport->key).'/national-teams?country='.$team->country->code
                : null,
            'upcoming' => EventItem::collection($upcoming)->resolve(),
            'limit' => $limit,
            'more' => $more,
            'competitions' => CompetitionItem::collection(
                Competition::query()->with(['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag'])->whereIn('id', $competitionIds)->orderBy('position')->get(),
            )->resolve(),
            'variants' => TeamItem::collection($variants)->resolve(),
            'tab' => $request->string('tab')->toString() === 'competitions' ? 'competitions' : 'upcoming',
        ]);
    }
}
