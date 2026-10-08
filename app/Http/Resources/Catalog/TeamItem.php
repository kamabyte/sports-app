<?php

namespace App\Http\Resources\Catalog;

use App\Sports\Catalog\Display;
use App\Sports\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Команда в поиске и списках: признаки (клуб/сборная, пол, возраст) отличают
 * «France», «France W», «France U21» и «France Ol.».
 *
 * @mixin Team
 */
class TeamItem extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => Display::name($this->resource),
            'original_name' => Display::originalName($this->resource),
            'url' => Display::teamUrl($this->resource),
            'logo' => $this->logo?->url,
            'national' => $this->kind === 'national',
            'code' => $this->code,
            'badges' => Display::teamBadges($this->resource),
            'sport' => $this->relationLoaded('sport') ? ['key' => $this->sport->key, 'name' => $this->sport->name] : null,
            'country' => $this->relationLoaded('country') && $this->country !== null
                ? ['name' => Display::name($this->country), 'flag' => Display::flag($this->country), 'code' => $this->country->code]
                : null,
        ];
    }
}
