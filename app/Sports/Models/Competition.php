<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Турнир — постоянная сущность («Премьер-лига»); розыгрыши — Edition.
 *
 * @property int $id
 * @property int $sport_id
 * @property string $name
 * @property string|null $short_name
 * @property string $slug
 * @property int $primary_category_id
 * @property string $kind
 * @property string $scope
 * @property string $participant_kind
 * @property string $entry_format
 * @property string|null $gender
 * @property string|null $age_group
 * @property int|null $logo_asset_id
 * @property string $identity_state
 * @property int $position
 */
class Competition extends CatalogModel
{
    protected $table = 'catalog_competitions';

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'logo_asset_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'catalog_competition_category');
    }

    /**
     * @return HasMany<Edition, $this>
     */
    public function editions(): HasMany
    {
        return $this->hasMany(Edition::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return MorphMany<Name, $this>
     */
    public function names(): MorphMany
    {
        return $this->morphMany(Name::class, 'nameable');
    }
}
