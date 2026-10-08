<?php

namespace App\Sports\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Страница турнира у источника: откуда известна, играют ли там сегодня-завтра,
 * когда загружалась.
 *
 * @property int $id
 * @property int $sport_id
 * @property string $path
 * @property string $origin
 * @property string|null $listed_as
 * @property CarbonImmutable|null $listed_on
 * @property CarbonImmutable|null $imported_at
 * @property CarbonImmutable|null $translated_at
 * @property CarbonImmutable|null $failed_at
 * @property string|null $error
 */
class Page extends CatalogModel
{
    protected $table = 'catalog_pages';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'listed_on' => 'date',
            'imported_at' => 'datetime',
            'translated_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }
}
