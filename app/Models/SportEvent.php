<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Матч из расписания (один на все источники — fixtures.sport_event_id);
 * телегид добавляет к нему показы (airings). Время — начало матча.
 *
 * @property int $id
 * @property int|null $fixture_id
 * @property string $sport
 * @property string $title
 * @property string|null $home
 * @property string|null $away
 * @property string|null $competition
 * @property string|null $competition_key
 * @property string|null $country
 * @property int $weight
 * @property string|null $round
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $search
 * @property-read Fixture|null $fixture
 * @property int|null $home_team_id
 * @property int|null $away_team_id
 * @property int|null $competition_id
 * @property-read Team|null $homeTeam
 * @property-read Team|null $awayTeam
 * @property-read Competition|null $competitionModel
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['home_team_id', 'away_team_id', 'competition_id', 'fixture_id', 'sport', 'title', 'home', 'away', 'competition', 'competition_key', 'country', 'weight', 'round', 'starts_at', 'ends_at', 'search'])]
class SportEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'weight' => 'integer',
        ];
    }

    /**
     * @return HasMany<Airing, $this>
     */
    public function airings(): HasMany
    {
        return $this->hasMany(Airing::class);
    }

    /**
     * @return BelongsTo<Fixture, $this>
     */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
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

    /**
     * Матч в разных источниках: KHL, ESPN, LiveScore… Основной — fixture_id.
     *
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }
}
