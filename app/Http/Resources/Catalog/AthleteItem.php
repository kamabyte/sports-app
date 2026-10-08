<?php

namespace App\Http\Resources\Catalog;

use App\Sports\Catalog\Display;
use App\Sports\Models\Athlete;
use App\Sports\Models\AthleteNationality;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Спортсмен: имя и флаг, под которым он выступает, — только если источник его показал.
 *
 * @mixin Athlete
 */
class AthleteItem extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AthleteNationality|null $nationality */
        $nationality = $this->relationLoaded('nationalities') ? $this->nationalities->first() : null;
        $country = $nationality?->country;

        return [
            'id' => $this->id,
            'name' => Display::name($this->resource),
            'original_name' => Display::originalName($this->resource),
            'url' => Display::athleteUrl($this->resource),
            'portrait' => $this->relationLoaded('portrait') ? $this->portrait?->url : null,
            'country' => $country !== null ? ['name' => Display::name($country), 'flag' => Display::flag($country), 'code' => $country->code] : null,
            'sports' => $this->relationLoaded('sports') ? $this->sports->map(fn ($sport) => ['key' => $sport->key, 'name' => $sport->name])->all() : null,
        ];
    }
}
