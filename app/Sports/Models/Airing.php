<?php

namespace App\Sports\Models;

use App\Models\Channel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Показ события на канале: передача телегида, узнанная как это событие.
 * Прямой эфир (во время события) или повтор (позже).
 *
 * @property int $id
 * @property int $event_id
 * @property int $channel_id
 * @property string $title
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $live
 * @property bool $replay
 * @property-read Channel $channel
 * @property-read Event $event
 */
class Airing extends CatalogModel
{
    protected $table = 'catalog_airings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'live' => 'boolean',
            'replay' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
