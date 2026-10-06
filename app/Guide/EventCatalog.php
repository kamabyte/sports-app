<?php

namespace App\Guide;

use App\Guide\Data\Competition;
use App\Guide\Data\Event;
use App\Guide\Data\Sport;
use Illuminate\Support\Collection;

/**
 * Источник спортивных событий. Сейчас — FakeEventCatalog; дальше —
 * каталог, который сопоставляет телегид Dispatcharr (и других источников)
 * со спортивными событиями.
 */
interface EventCatalog
{
    /**
     * @return Collection<int, Sport>
     */
    public function sports(): Collection;

    /**
     * @return Collection<int, Competition>
     */
    public function competitions(?string $sport = null): Collection;

    /**
     * События по возрастанию времени начала.
     *
     * @return Collection<int, Event>
     */
    public function events(EventQuery $query): Collection;

    public function find(string $id): ?Event;
}
