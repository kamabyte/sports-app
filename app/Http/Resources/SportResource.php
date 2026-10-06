<?php

namespace App\Http\Resources;

use App\Guide\Data\Sport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sport
 */
class SportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'emoji' => $this->emoji,
            'color' => $this->color,
        ];
    }
}
