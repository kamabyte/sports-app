<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Sports\Catalog\Pages;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Главная: виды спорта с числом событий на неделю вперёд и ближайшие
 * события каждого вида спорта (сегодня, иначе — ближайший день с событиями).
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('catalog/home', Pages::home());
    }
}
