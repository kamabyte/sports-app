<?php

namespace App\Sports\Catalog;

use App\Sports\CatalogDatabase;
use App\Sports\Models\Coverage;
use App\Sports\Models\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Общие данные интерфейса каталога: виды спорта для меню (с данными и без)
 * и когда каталог последний раз обновлялся.
 */
final class Navigation
{
    /**
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        if (! Schema::connection(CatalogDatabase::connectionName())->hasTable('catalog_sports')) {
            return ['sports' => [], 'updated_at' => null, 'stale' => false];
        }

        $sports = Sport::query()->orderBy('position')->get();
        $configured = array_map(fn ($key) => (string) $key, array_keys(config('sports.catalog.sports')));
        $updated = Coverage::query()->max('last_success_at');
        $updatedAt = $updated !== null ? CarbonImmutable::parse($updated, 'UTC') : null;

        return [
            'sports' => collect($configured)->map(function (string $key) use ($sports) {
                $sport = $sports->firstWhere('key', $key);

                return [
                    'key' => $key,
                    'name' => $sport->name ?? config("sports.catalog.sports.{$key}.name"),
                    'family' => config("sports.catalog.sports.{$key}.family"),
                    'url' => Display::sportUrl($key),
                    'has_data' => (bool) $sport?->active,
                ];
            })->values()->all(),
            'updated_at' => $updatedAt?->toIso8601String(),
            'stale' => $updatedAt !== null && $updatedAt->lt(CarbonImmutable::now()->subHours((int) config('sports.catalog.stale_hours'))),
        ];
    }
}
