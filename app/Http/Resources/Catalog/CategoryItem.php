<?php

namespace App\Http\Resources\Catalog;

use App\Sports\Catalog\Display;
use App\Sports\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Раздел навигации: страна, регион, тур, весовая категория, игра.
 *
 * @mixin Category
 */
class CategoryItem extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'kind' => $this->kind,
            'name' => Display::name($this->resource),
            'url' => Display::categoryUrl($this->sport->key, $this->resource),
            'flag' => Display::flag($this->country),
            'parent_key' => $this->parent_id !== null ? $this->parent?->key : null,
            'competitions_count' => $this->whenCounted('competitions'),
        ];
    }
}
