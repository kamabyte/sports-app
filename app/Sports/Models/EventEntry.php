<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Участник события: сторона (1/2) в очной встрече или позиция в гонке.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $entry_id
 * @property int|null $side
 * @property int $position
 * @property string|null $home_away
 * @property string|null $label
 * @property string|null $entry_status
 * @property string|null $affiliation
 */
class EventEntry extends CatalogModel
{
    protected $table = 'catalog_event_entries';

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
