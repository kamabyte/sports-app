<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Поставщик данных (flashscore).
 */
class Provider extends CatalogModel
{
    protected $table = 'catalog_providers';

    /**
     * @return HasMany<ImportRun, $this>
     */
    public function importRuns(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }
}
