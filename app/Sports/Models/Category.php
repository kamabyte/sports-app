<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Раздел навигации вида спорта: страна, регион, тур, весовая категория,
 * игра. Страна/регион раздела — только для навигации, не гражданство участников.
 *
 * @property int $id
 * @property int $sport_id
 * @property int|null $parent_id
 * @property string $kind
 * @property string $key
 * @property string $name
 * @property int|null $country_id
 * @property int|null $region_id
 * @property int $position
 */
class Category extends CatalogModel
{
    protected $table = 'catalog_categories';

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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
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
     * @return BelongsToMany<Competition, $this>
     */
    public function competitions(): BelongsToMany
    {
        return $this->belongsToMany(Competition::class, 'catalog_competition_category')->withPivot('position');
    }

    /**
     * @return MorphMany<Name, $this>
     */
    public function names(): MorphMany
    {
        return $this->morphMany(Name::class, 'nameable');
    }
}
