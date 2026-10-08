<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Команда: клуб или сборная. Мужская, женская, молодёжная и олимпийская
 * сборные — разные команды, даже с одинаковым названием и эмблемой.
 *
 * @property int $id
 * @property int $sport_id
 * @property string $name
 * @property string|null $short_name
 * @property string|null $code
 * @property string $kind
 * @property string|null $gender
 * @property string|null $age_group
 * @property string|null $squad
 * @property int|null $country_id
 * @property int|null $logo_asset_id
 * @property string $identity_state
 * @property string $slug
 */
class Team extends CatalogModel
{
    protected $table = 'catalog_teams';

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'logo_asset_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function parentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * @return MorphMany<Name, $this>
     */
    public function names(): MorphMany
    {
        return $this->morphMany(Name::class, 'nameable');
    }
}
