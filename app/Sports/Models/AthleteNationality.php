<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Под флагом какой страны выступает спортсмен — по данным источника.
 *
 * @property int $id
 * @property int $athlete_id
 * @property int $country_id
 */
class AthleteNationality extends CatalogModel
{
    protected $table = 'catalog_athlete_nationalities';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Athlete, $this>
     */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
