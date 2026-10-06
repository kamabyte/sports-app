<?php

namespace App\Guide\Data;

use Carbon\CarbonImmutable;

/**
 * Где показывают событие: показ на канале источника и запись телегида,
 * по которой событие с ним сопоставили. Бывает прямой эфир, одновременный
 * показ на другом канале и повтор (со своим временем).
 */
final readonly class Broadcast
{
    public function __construct(
        /** Номер трансляции (для адресов). */
        public int $id,
        public string $channel,
        public int $channelNumber,
        public ?string $country,
        public ?string $language,
        /** HD, FHD или 4K, если известно. */
        public ?string $quality,
        public string $provider,
        public string $epgTitle,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?string $logo = null,
        /** Помечен в телегиде как прямой эфир. */
        public bool $live = false,
        public bool $replay = false,
        /** Поток канала; null — потока нет (фейковый каталог). */
        public ?string $streamUrl = null,
    ) {}
}
