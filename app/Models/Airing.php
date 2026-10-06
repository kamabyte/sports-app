<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sport_event_id
 * @property int $channel_id
 * @property string $title
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $live
 * @property bool $replay
 * @property-read Channel $channel
 * @property-read SportEvent $sportEvent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sport_event_id', 'channel_id', 'title', 'starts_at', 'ends_at', 'live', 'replay'])]
class Airing extends Model
{
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
     * @return BelongsTo<SportEvent, $this>
     */
    public function sportEvent(): BelongsTo
    {
        return $this->belongsTo(SportEvent::class);
    }
}
