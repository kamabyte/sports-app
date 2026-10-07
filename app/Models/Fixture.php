<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Матч из спортивного API (или событие без соперников: сессия Формулы-1,
 * гонка биатлона — без home/away, с title). К нему привязываются события телегида
 * (sport_events.fixture_id): у матча — точное время, счёт и эмблемы.
 *
 * @property int $id
 * @property int|null $sport_event_id
 * @property string $source
 * @property string $external_id
 * @property string $sport
 * @property string|null $competition
 * @property string|null $competition_logo
 * @property string|null $round
 * @property string|null $home
 * @property string|null $away
 * @property string|null $title
 * @property string|null $home_logo
 * @property string|null $away_logo
 * @property Carbon $starts_at
 * @property string $status
 * @property int|null $home_score
 * @property int|null $away_score
 * @property int|null $home_team_id
 * @property int|null $away_team_id
 * @property int|null $competition_id
 * @property-read Team|null $homeTeam
 * @property-read Team|null $awayTeam
 * @property-read Competition|null $competitionModel
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sport_event_id', 'home_team_id', 'away_team_id', 'competition_id', 'source', 'external_id', 'sport', 'competition', 'competition_logo', 'round', 'title', 'home', 'away', 'home_logo', 'away_logo', 'starts_at', 'status', 'home_score', 'away_score'])]
class Fixture extends Model
{
    public const string UPCOMING = 'upcoming';

    public const string LIVE = 'live';

    public const string FINISHED = 'finished';

    public const string CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'home_score' => 'integer',
            'away_score' => 'integer',
        ];
    }

    /**
     * @return HasMany<SportEvent, $this>
     */
    public function sportEvents(): HasMany
    {
        return $this->hasMany(SportEvent::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * Турнир из каталога; `competition` — текст из источника.
     *
     * @return BelongsTo<Competition, $this>
     */
    public function competitionModel(): BelongsTo
    {
        return $this->belongsTo(Competition::class, 'competition_id');
    }
}
