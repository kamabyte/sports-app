<?php

namespace App\Http\Resources\Catalog;

use App\Sports\Catalog\Display;
use App\Sports\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Турнир в списках и заголовках групп: название, раздел (страна/тур) и признаки.
 *
 * @mixin Competition
 */
class CompetitionItem extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->primaryCategory;

        return [
            'id' => $this->id,
            'name' => Display::name($this->resource),
            'original_name' => Display::originalName($this->resource),
            'url' => Display::competitionUrl($this->sport->key, $this->resource),
            'logo' => $this->logo?->url,
            'sport' => ['key' => $this->sport->key, 'name' => $this->sport->name, 'family' => $this->sport->family],
            'category' => [
                'key' => $category->key,
                'name' => Display::name($category),
                'kind' => $category->kind,
                'url' => Display::categoryUrl($this->sport->key, $category),
                'flag' => Display::flag($category->country),
            ],
            'badges' => Display::competitionBadges($this->resource),
        ];
    }
}
