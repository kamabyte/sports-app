<?php

namespace App\Guide;

use App\Models\Airing;
use App\Models\Channel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Каналы источника для боковой панели и раздела «Каналы»: какие показывают
 * матчи из расписания и что на них идёт сейчас.
 */
final class Channels
{
    /**
     * Каналы, где впереди (или сейчас) есть матчи, — по номеру.
     *
     * @return Collection<int, Channel>
     */
    public function withMatches(): Collection
    {
        return Channel::query()
            ->whereHas('airings', fn (Builder $query) => $query->where('ends_at', '>', now()))
            ->withExists(['airings as on_air' => fn (Builder $query) => $query->where('starts_at', '<=', now())->where('ends_at', '>', now())])
            ->orderBy('number')
            ->get();
    }

    /**
     * Все каналы с тем, что на них идёт из расписания сейчас (если идёт).
     *
     * @return Collection<int, Channel>
     */
    public function all(): Collection
    {
        return Channel::query()
            ->with(['airings' => fn ($query) => $query
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->with('sportEvent')])
            ->orderBy('number')
            ->get();
    }

    /**
     * Матчи канала: что идёт сейчас и что впереди, по времени.
     *
     * @return Collection<int, Airing>
     */
    public function schedule(Channel $channel): Collection
    {
        return $channel->airings()
            ->where('ends_at', '>', now())
            ->with('sportEvent')
            ->orderBy('starts_at')
            ->get();
    }
}
