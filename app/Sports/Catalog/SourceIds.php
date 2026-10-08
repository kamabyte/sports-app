<?php

namespace App\Sports\Catalog;

use App\Sports\CatalogDatabase;
use Carbon\CarbonImmutable;

/**
 * Идентификаторы источника → наши id: по таблице catalog_{entity}_sources.
 * Пространство имён (external_scope) обязательно: id участника и id матча
 * у Flashscore — разные множества. Регистр значим.
 */
final class SourceIds
{
    public const array ENTITIES = ['country', 'category', 'team', 'athlete', 'competition', 'edition', 'stage', 'event'];

    public static function find(string $entity, int $providerId, string $scope, string $externalId): ?int
    {
        $id = CatalogDatabase::connection()->table(self::table($entity))
            ->where('provider_id', $providerId)
            ->where('external_scope', $scope)
            ->where('external_id', $externalId)
            ->value("{$entity}_id");

        return $id === null ? null : (int) $id;
    }

    /**
     * Привязать id источника к сущности. Уже привязанный к другой сущности id
     * не перепривязывается молча — это ошибка разрешения, её надо разобрать.
     */
    public static function link(string $entity, int $providerId, string $scope, string $externalId, int $id, ?string $url = null): void
    {
        $table = CatalogDatabase::connection()->table(self::table($entity));
        $now = CarbonImmutable::now();
        $existing = (clone $table)->where('provider_id', $providerId)->where('external_scope', $scope)->where('external_id', $externalId)->first();

        if ($existing === null) {
            $table->insert([
                'provider_id' => $providerId,
                'external_scope' => $scope,
                'external_id' => $externalId,
                "{$entity}_id" => $id,
                'url' => $url,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        if ((int) $existing->{"{$entity}_id"} !== $id) {
            throw new InvalidCatalogData("{$entity} {$scope}:{$externalId} already belongs to #{$existing->{"{$entity}_id"}}, not #{$id}");
        }

        $table->where('id', $existing->id)->update(['last_seen_at' => $now, 'updated_at' => $now] + ($url !== null ? ['url' => $url] : []));
    }

    /**
     * Все id источника сущности — для диагностики и страницы «откуда данные».
     *
     * @return list<array{scope: string, id: string, url: string|null}>
     */
    public static function of(string $entity, int $id): array
    {
        return array_values(CatalogDatabase::connection()->table(self::table($entity))
            ->where("{$entity}_id", $id)
            ->get()
            ->map(fn (object $row) => ['scope' => (string) $row->external_scope, 'id' => (string) $row->external_id, 'url' => $row->url !== null ? (string) $row->url : null])
            ->all());
    }

    private static function table(string $entity): string
    {
        if (! in_array($entity, self::ENTITIES, true)) {
            throw new InvalidCatalogData("Unknown source entity {$entity}");
        }

        return "catalog_{$entity}_sources";
    }
}
