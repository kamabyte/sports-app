<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Вид спорта. family — модель события и страница матча:
 * team (команды), racket (теннис, настольный теннис, бадминтон — сеты),
 * duel (снукер, дартс — фреймы/сеты), bout (бой), cricket, race, golf.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $family
 * @property string $wave
 * @property bool $active
 * @property int $position
 */
class Sport extends CatalogModel
{
    protected $table = 'catalog_sports';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Competition, $this>
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    /**
     * @return MorphMany<Name, $this>
     */
    public function names(): MorphMany
    {
        return $this->morphMany(Name::class, 'nameable');
    }
}
