<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Дисциплина внутри вида спорта: игра в киберспорте, биатлон в зимних видах.
 *
 * @property int $id
 * @property int $sport_id
 * @property string $key
 * @property string $name
 */
class Discipline extends CatalogModel
{
    protected $table = 'catalog_disciplines';

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }
}
