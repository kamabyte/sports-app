<?php

namespace App\Sports;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Где лежит каталог и в каком режиме приложение: самостоятельный каталог
 * (standalone) или прежнее приложение «матчи с трансляциями» (iptv).
 */
final class CatalogDatabase
{
    public static function connectionName(): string
    {
        return (string) config('database.default');
    }

    public static function connection(): ConnectionInterface
    {
        return DB::connection(self::connectionName());
    }

    public static function standalone(): bool
    {
        return config('sports.mode') === 'standalone';
    }
}
