<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Заявка — единица участия: команда, спортсмен, пара, эстафета.
 *
 * @property int $id
 * @property int $sport_id
 * @property string $kind
 * @property int|null $team_id
 * @property string $resolution_key
 */
class Entry extends CatalogModel
{
    protected $table = 'catalog_entries';

    /**
     * @return BelongsTo<Sport, $this>
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<EntryMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(EntryMember::class);
    }

    /**
     * @return BelongsToMany<Athlete, $this>
     */
    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(Athlete::class, 'catalog_entry_members')->withPivot('position')->orderByPivot('position');
    }
}
