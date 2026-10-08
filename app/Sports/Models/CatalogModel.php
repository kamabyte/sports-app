<?php

namespace App\Sports\Models;

use App\Sports\CatalogDatabase;
use Illuminate\Database\Eloquent\Model;

/**
 * Модель каталога: соединение — живой каталог или демо-база (CatalogDatabase).
 * Поля заполняются только кодом импорта и сидера, не из запросов пользователя.
 */
abstract class CatalogModel extends Model
{
    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return CatalogDatabase::connectionName();
    }
}
