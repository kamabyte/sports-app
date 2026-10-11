<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Pages;
use App\Sports\Catalog\Paging;
use App\Sports\Models\Athlete;
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

        return Inertia::render('catalog/athlete', Pages::athlete($athlete, Paging::limit($request, 30)) + [
            'tab' => $request->string('tab')->toString() === 'competitions' ? 'competitions' : 'upcoming',
        ]);
    }
}
