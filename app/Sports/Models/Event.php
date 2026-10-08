<?php

namespace App\Sports\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Событие каталога: матч, бой, сессия, гонка, турнир с таблицей лидеров.
 * Время неизвестно — time_precision = date/unknown, а не полночь.
 *
 * @property int $id
 * @property int $competition_id
 * @property int|null $edition_id
 * @property int|null $stage_id
 * @property int|null $parent_id
 * @property string $kind
 * @property string|null $name
 * @property string|null $name_local
 * @property string|null $round_label
 * @property string|null $round_label_local
 * @property CarbonImmutable|null $date
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string|null $raw_status
 * @property string $publication_state
 * @property string $time_precision
 * @property string $status
 * @property string|null $status_detail
 * @property string $result_state
 * @property int|null $winner_side
 * @property string|null $venue
 * @property string|null $result_text
 * @property CarbonImmutable|null $last_observed_at
 */
class Event extends CatalogModel
{
    protected $table = 'catalog_events';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_observed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'parent_id');
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Event::class, 'parent_id');
    }

    /**
     * @return HasMany<EventEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(EventEntry::class)->orderBy('side')->orderBy('position');
    }
}
