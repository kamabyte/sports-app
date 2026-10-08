<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\AthleteItem;
use App\Http\Resources\Catalog\CompetitionItem;
use App\Http\Resources\Catalog\TeamItem;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\Names;
use App\Sports\Models\Athlete;
use App\Sports\Models\Competition;
use App\Sports\Models\Name;
use App\Sports\Models\Sport;
use App\Sports\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Поиск по всем названиям — английским, русским, псевдонимам. Одно и то же
 * название у разных сущностей (сборная Франции и её женская сборная,
 * теннисист и команда) — разные результаты с подписями: вид спорта, раздел,
 * пол и возраст.
 */
class SearchController extends Controller
{
    private const int LIMIT = 40;

    public function __invoke(Request $request): Response
    {
        $query = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $normalized = Names::normalize($query);
        $results = ['sports' => [], 'competitions' => [], 'teams' => [], 'national_teams' => [], 'players' => []];

        if (mb_strlen($normalized) >= 2) {
            $matches = Name::query()
                ->where(fn ($q) => $q->where('normalized', 'like', "{$normalized}%")->orWhere('normalized', 'like', "% {$normalized}%"))
                ->limit(600)
                ->get(['nameable_type', 'nameable_id'])
                ->groupBy('nameable_type')
                ->map(fn ($rows) => $rows->pluck('nameable_id')->unique()->values()->all());
            // И по собственному названию сущности — если отдельной записи о названии нет.
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
            foreach (['team' => Team::class, 'athlete' => Athlete::class, 'competition' => Competition::class] as $type => $model) {
                $own = $model::query()->where('name', 'like', $like)->limit(200)->pluck('id')->all();
                $matches->put($type, array_values(array_unique([...$matches->get($type, []), ...$own])));
            }

            $results['sports'] = Sport::query()->whereIn('id', $matches->get('sport', []))->orderBy('position')->get()
                ->map(fn (Sport $sport) => ['key' => $sport->key, 'name' => $sport->name, 'url' => Display::sportUrl($sport->key), 'has_data' => $sport->active])->all();
            $results['competitions'] = CompetitionItem::collection(
                Competition::query()->with(['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag'])
                    ->whereIn('id', $matches->get('competition', []))->orderBy('position')->limit(self::LIMIT)->get(),
            )->resolve();
            $teams = Team::query()->with(['sport', 'logo', 'names' => Display::names(), 'country.flag', 'country.names' => Display::names()])
                ->whereIn('id', $matches->get('team', []))->orderBy('name')->limit(self::LIMIT * 2)->get();
            $results['teams'] = TeamItem::collection($teams->where('kind', '!=', 'national')->take(self::LIMIT)->values())->resolve();
            $results['national_teams'] = TeamItem::collection($teams->where('kind', 'national')->take(self::LIMIT)->values())->resolve();
            $results['players'] = AthleteItem::collection(
                Athlete::query()->with(['names' => Display::names(), 'sports', 'nationalities.country.flag', 'nationalities.country.names' => Display::names()])
                    ->whereIn('id', $matches->get('athlete', []))->orderBy('name')->limit(self::LIMIT)->get(),
            )->resolve();
        }

        return Inertia::render('catalog/search', [
            'query' => $query,
            'too_short' => $query !== '' && mb_strlen($normalized) < 2,
            'results' => $results,
        ]);
    }
}
