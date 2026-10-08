<?php

namespace App\Sports\Catalog;

use App\Http\Resources\Catalog\CompetitionItem;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Sport;
use App\Sports\Models\Team;

/**
 * Боковая панель вида спорта: популярные турниры и все разделы (страны,
 * регионы, туры) — видна всегда, даже если сегодня событий нет.
 */
final class SportPanel
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Sport $sport): array
    {
        $categories = Category::query()
            ->with(['sport', 'country.flag', 'names' => Display::names()])
            ->withCount('competitions')
            ->where('sport_id', $sport->id)
            ->get();

        $item = fn (Category $category) => [
            'key' => $category->key,
            'kind' => $category->kind,
            'name' => (string) Display::name($category),
            'url' => Display::categoryUrl($sport->key, $category),
            'flag' => Display::flag($category->country),
            'competitions_count' => $category->competitions_count,
        ];
        $sorted = fn ($items) => $items->sortBy(fn (Category $category) => sprintf('%04d-%s', $category->position, mb_strtolower((string) Display::name($category))));

        return [
            'popular' => CompetitionItem::collection(
                Competition::query()
                    ->with(['sport', 'logo', 'names' => Display::names(), 'primaryCategory.names' => Display::names(), 'primaryCategory.country.flag'])
                    ->where('sport_id', $sport->id)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->limit(8)
                    ->get(),
            )->resolve(),
            'categories' => $sorted($categories->whereNull('parent_id'))->map(fn (Category $category) => $item($category) + [
                'children' => $sorted($categories->where('parent_id', $category->id))->map($item)->values()->all(),
            ])->values()->all(),
            'has_national_teams' => Team::query()->where('sport_id', $sport->id)->where('kind', 'national')->exists(),
            'has_players' => in_array($sport->family, ['racket', 'duel', 'bout', 'race', 'golf'], true),
        ];
    }
}
