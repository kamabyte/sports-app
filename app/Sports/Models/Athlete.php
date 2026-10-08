<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Спортсмен. Пара в теннисе — это заявка (Entry) из двух спортсменов, а не команда.
 *
 * @property int $id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $gender
 * @property int|null $portrait_asset_id
 * @property string $identity_state
 * @property string $slug
 */
class Athlete extends CatalogModel
{
    protected $table = 'catalog_athletes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function portrait(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'portrait_asset_id');
    }

    /**
     * @return HasMany<AthleteNationality, $this>
     */
    public function nationalities(): HasMany
    {
        return $this->hasMany(AthleteNationality::class);
    }

    /**
     * @return BelongsToMany<Sport, $this>
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'catalog_athlete_sports');
    }

    /**
     * @return BelongsToMany<Entry, $this>
     */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'catalog_entry_members')->withPivot('position');
    }

    /**
     * @return MorphMany<Name, $this>
     */
    public function names(): MorphMany
    {
        return $this->morphMany(Name::class, 'nameable');
    }
}
