<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Канал источника. stream_id — id потока в Xtream Codes.
 *
 * @property int $id
 * @property int $stream_id
 * @property int $number
 * @property string $name
 * @property string|null $logo
 * @property string|null $tvg_id tvg-id из плейлиста Dispatcharr
 * @property string|null $guide_source ключ телегида (sports.guides.sources)
 * @property string|null $guide_channel id канала в этом телегиде
 * @property string|null $category
 * @property string|null $language
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['stream_id', 'number', 'name', 'logo', 'tvg_id', 'guide_source', 'guide_channel', 'category', 'language'])]
class Channel extends Model
{
    /**
     * @return HasMany<Airing, $this>
     */
    public function airings(): HasMany
    {
        return $this->hasMany(Airing::class);
    }
}
