<?php

namespace App\Models;

use App\Guide\Logos\HasStoredLogo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Турнир из курируемого списка (CompetitionCatalog); эмблема — из источников.
 *
 * @property int $id
 * @property string $key
 * @property string $sport
 * @property string $name
 * @property string $short_name
 * @property string|null $country
 * @property int $weight
 * @property string|null $logo_url
 * @property string|null $logo_path
 * @property Carbon|null $logo_failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'sport', 'name', 'short_name', 'country', 'weight', 'logo_url', 'logo_path', 'logo_failed_at'])]
class Competition extends Model
{
    use HasStoredLogo;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['logo_failed_at' => 'datetime', 'weight' => 'integer'];
    }
}
