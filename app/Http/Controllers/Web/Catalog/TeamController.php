<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use App\Sports\Catalog\Paging;
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

        return Inertia::render('catalog/team', Pages::team($team, Paging::limit($request, 30)) + [
            'national_teams_url' => $team->kind === 'national' && $team->country !== null
                ? Display::sportUrl($team->sport->key).'/national-teams?country='.$team->country->code
                : null,
            'tab' => $request->string('tab')->toString() === 'competitions' ? 'competitions' : 'upcoming',
        ]);
    }
}
