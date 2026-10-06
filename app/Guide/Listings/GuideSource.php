<?php

namespace App\Guide\Listings;

/**
 * Телегид XMLTV из sports.guides.sources.
 */
final readonly class GuideSource
{
    public function __construct(
        public string $key,
        public string $name,
        public string $url,
        /** Каналы без страны (FAST-сервисы): ищем в нём и каналы других стран. */
        public bool $anyCountry = false,
    ) {}

    /**
     * @param  array<string, array{name?: string, url: string, any_country?: bool}>  $config
     * @return list<self>
     */
    public static function fromConfig(array $config): array
    {
        return array_map(
            fn (string $key, array $source) => new self($key, $source['name'] ?? $key, $source['url'], $source['any_country'] ?? false),
            array_keys($config),
            $config,
        );
    }
}
