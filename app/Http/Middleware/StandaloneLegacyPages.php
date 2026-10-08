<?php

namespace App\Http\Middleware;

use App\Sports\CatalogDatabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * В самостоятельном режиме старые страницы «матчей с трансляциями» ведут в
 * новый каталог: вид спорта — на его раздел, матч — в расписание (связи
 * старых матчей с событиями каталога появятся после переноса данных).
 */
class StandaloneLegacyPages
{
    /** Старый ключ вида спорта → новый. */
    private const array SPORTS = [
        'combat' => 'mma',
        'rugby' => 'rugby-union',
        'winter' => 'winter-sports',
        'biathlon' => 'winter-sports',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! CatalogDatabase::standalone()) {
            return $next($request);
        }

        $sport = $request->route('sport');
        if (is_string($sport)) {
            $key = self::SPORTS[$sport] ?? $sport;

            return redirect(array_key_exists($key, config('sports.catalog.sports')) ? "/sport/{$key}" : '/');
        }

        return redirect('/schedule');
    }
}
