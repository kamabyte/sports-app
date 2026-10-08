<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Страна или спортивная территория (Англия, Шотландия). Не путать с
 * разделом навигации и местом проведения.
 *
 * @property int $id
 * @property string $code
 * @property string|null $iso2
 * @property string $kind
 * @property string $name
 * @property int|null $flag_asset_id
 */
class Country extends CatalogModel
{
    protected $table = 'catalog_countries';

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function flag(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'flag_asset_id');
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'parent_id');
    }

    /**
     * @return MorphMany<Name, $this>
     */
    public function names(): MorphMany
    {
        return $this->morphMany(Name::class, 'nameable');
    }
}
