<?php

namespace App\Models;

use App\Guide\Logos\HasStoredLogo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Команда (или спортсмен) — одна на все написания в разных источниках.
 *
 * @property int $id
 * @property string $sport
 * @property string $name
 * @property string|null $logo_url
 * @property string|null $logo_source
 * @property string|null $logo_path
 * @property Carbon|null $logo_failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['sport', 'name', 'logo_url', 'logo_source', 'logo_path', 'logo_failed_at'])]
class Team extends Model
{
    use HasStoredLogo;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['logo_failed_at' => 'datetime'];
    }

    /**
     * @return HasMany<TeamAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(TeamAlias::class);
    }
}
