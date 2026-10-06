<?php

namespace App\Http\Resources;

use App\Guide\Data\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'short_name' => $this->shortName,
            'color' => $this->color,
            'logo' => $this->logo,
            'flag' => $this->flag,
        ];
    }
}
