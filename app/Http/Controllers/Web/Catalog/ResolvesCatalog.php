<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Sports\Catalog\Display;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Sport;

/**
 * Разделы каталога по адресу: /sport/{вид}/{раздел}/{турнир}. Сущности
 * ищутся вручную, а не привязкой маршрута, — после выбора демо/живых данных.
 */
trait ResolvesCatalog
{
    protected function sport(string $key): Sport
    {
        return Sport::query()->where('key', $key)->first() ?? abort(404);
    }

    protected function category(Sport $sport, string $key): Category
    {
        return Category::query()
            ->with(['sport', 'country.flag', 'names' => Display::names(), 'parent'])
            ->where('sport_id', $sport->id)
            ->where('key', $key)
            ->first() ?? abort(404);
    }

    /** Турнир раздела: основного — или того, где он тоже виден (адрес всё равно канонический). */
    protected function competition(Category $category, string $slug): Competition
    {
        return Competition::query()
            ->with(['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag'])
            ->where('slug', $slug)
            ->where(fn ($query) => $query
                ->where('primary_category_id', $category->id)
                ->orWhereHas('categories', fn ($c) => $c->whereKey($category->id)))
            ->orderByRaw('primary_category_id = ? desc', [$category->id])
            ->first() ?? abort(404);
    }

    /**
     * Хлебные крошки: Вид спорта → раздел (с родителем) → турнир → …
     *
     * @return list<array{label: string, url: string|null}>
     */
    protected function crumbs(Sport $sport, ?Category $category = null, ?Competition $competition = null): array
    {
        $crumbs = [['label' => $sport->name, 'url' => Display::sportUrl($sport->key)]];
        if ($category?->parent !== null) {
            $crumbs[] = ['label' => (string) Display::name($category->parent), 'url' => Display::categoryUrl($sport->key, $category->parent)];
        }
        if ($category !== null) {
            $crumbs[] = ['label' => (string) Display::name($category), 'url' => Display::categoryUrl($sport->key, $category)];
        }
        if ($competition !== null) {
            $crumbs[] = ['label' => (string) Display::name($competition), 'url' => Display::competitionUrl($sport->key, $competition)];
        }

        return $crumbs;
    }
}
