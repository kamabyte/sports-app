<?php

namespace App\Sports\Models;

/**
 * Изображение с источником: logo, flag или portrait.
 *
 * @property int $id
 * @property string $kind
 * @property string $url
 * @property string|null $path
 */
class Asset extends CatalogModel
{
    protected $table = 'catalog_assets';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
