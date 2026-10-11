<?php

namespace App\Http\Middleware;

use App\Sports\CatalogDatabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Прежний API «матчей с трансляциями» работает только в режиме iptv. В
 * самостоятельном режиме его данные не обновляются — клиент получает 410
 * и адрес API каталога, а не устаревшее расписание.
 */
class LegacyApi
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(CatalogDatabase::standalone(), Response::HTTP_GONE, 'Use /api/v1 (catalog API).');

        return $next($request);
    }
}
