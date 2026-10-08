<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Континент или «Мир»: раздел навигации, а не страна.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 */
class Region extends CatalogModel
{
    protected $table = 'catalog_regions';

    /**
     * @return BelongsTo<Region, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }

    /**
     * @return MorphMany<Name, $this>
     */
    public function names(): MorphMany
    {
        return $this->morphMany(Name::class, 'nameable');
    }
}
