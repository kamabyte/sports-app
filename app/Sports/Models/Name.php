<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Название сущности на языке: перевод, сокращение, псевдоним. Источник и
 * ручные правки (curated) сохраняются.
 *
 * @property int $id
 * @property string $locale
 * @property string $kind
 * @property string $name
 * @property string $normalized
 * @property bool $curated
 */
class Name extends CatalogModel
{
    protected $table = 'catalog_names';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'curated' => 'boolean',
            'observed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function nameable(): MorphTo
    {
        return $this->morphTo();
    }
}
