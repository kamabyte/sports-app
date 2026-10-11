<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Sports\Catalog\Pages;
use Illuminate\Http\JsonResponse;

/**
 * Главная ТВ-клиента — та же, что у веб-клиента: трансляции сегодня и
 * ближайшие события каждого вида спорта.
 */
class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(Pages::home());
    }
}
