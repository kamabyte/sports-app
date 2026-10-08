<?php

namespace App\Sports\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Розыгрыш турнира: сезон «2026/2027» или турнир года «2026».
 *
 * @property int $id
 * @property int $competition_id
 * @property string $key
 * @property string $label
 * @property bool $is_current
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 */
class Edition extends CatalogModel
{
    protected $table = 'catalog_editions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return HasMany<Stage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
