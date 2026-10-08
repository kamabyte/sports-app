<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Catalog\HomeController as CatalogHome;
use App\Http\Controllers\Web\Catalog\ScheduleController as CatalogSchedule;
use App\Http\Controllers\Web\Catalog\SearchController as CatalogSearch;
use App\Sports\CatalogDatabase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Главная, расписание и поиск — в самостоятельном режиме из каталога, в
 * режиме iptv — прежние страницы «матчей с трансляциями». Режим — настройка
 * SPORTS_MODE: возврат к прежнему приложению не требует правки кода.
 */
class ModeController extends Controller
{
    public function home(): Response
    {
        return $this->dispatch(CatalogHome::class, HomeController::class);
    }

    public function schedule(): Response
    {
        return $this->dispatch(CatalogSchedule::class, ScheduleController::class);
    }

    public function search(): Response
    {
        return $this->dispatch(CatalogSearch::class, SearchController::class);
    }

    /**
     * @param  class-string  $catalog
     * @param  class-string  $legacy
     */
    private function dispatch(string $catalog, string $legacy): Response
    {
        $response = app()->call(CatalogDatabase::standalone() ? $catalog : $legacy);

        return $response instanceof Response ? $response : $response->toResponse(request());
    }
}
