<?php

namespace App\Sports\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Стадия розыгрыша: группа, квалификация, основная сетка, плей-офф, сессия.
 *
 * @property int $id
 * @property int $edition_id
 * @property int|null $parent_id
 * @property string $kind
 * @property string $name
 * @property int $position
 */
class Stage extends CatalogModel
{
    protected $table = 'catalog_stages';

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return BelongsTo<Stage, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'parent_id');
    }
}
