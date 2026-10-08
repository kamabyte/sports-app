<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Спортсмен в составе заявки (пары, эстафеты) и его порядок.
 *
 * @property int $id
 * @property int $entry_id
 * @property int $athlete_id
 * @property int $position
 */
class EntryMember extends CatalogModel
{
    protected $table = 'catalog_entry_members';

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<Athlete, $this>
     */
    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
