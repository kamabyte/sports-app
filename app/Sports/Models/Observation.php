<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Загруженная страница источника и результат её проверки.
 *
 * @property int $id
 * @property string $url
 * @property string $status
 */
class Observation extends CatalogModel
{
    protected $table = 'catalog_observations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_cache' => 'boolean',
            'observed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImportRun, $this>
     */
    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
