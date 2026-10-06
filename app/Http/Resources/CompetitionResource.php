<?php

namespace App\Http\Resources;

use App\Guide\Data\Competition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Competition
 */
class CompetitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'sport' => $this->sport,
            'name' => $this->name,
            'short_name' => $this->shortName,
            'country' => $this->country,
            'logo' => $this->logo,
        ];
    }
}
