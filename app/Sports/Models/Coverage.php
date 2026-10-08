<?php

namespace App\Sports\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Свежесть турнира: последний успешный импорт и последняя ошибка.
 *
 * @property int $id
 * @property int $competition_id
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $last_failure_at
 * @property string|null $last_error
 * @property int $events
 * @property CarbonImmutable|null $window_from
 * @property CarbonImmutable|null $window_to
 */
class Coverage extends CatalogModel
{
    protected $table = 'catalog_coverage';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'window_from' => 'date',
            'window_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
