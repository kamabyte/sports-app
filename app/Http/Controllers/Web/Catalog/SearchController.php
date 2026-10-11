<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Sports\Catalog\Pages;
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
    public function __invoke(Request $request): Response
    {
        $query = is_string($request->query('q')) ? trim($request->query('q')) : '';

        return Inertia::render('catalog/search', Pages::search($query));
    }
}
