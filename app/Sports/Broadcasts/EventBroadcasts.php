<?php

namespace App\Sports\Broadcasts;

use App\Guide\Data\Broadcast;
use App\Guide\Dispatcharr\XtreamClient;
use App\Sports\Models\Airing;
use App\Sports\Models\Event;
use Carbon\CarbonImmutable;

/**
 * Трансляции события каталога: ещё не закончившиеся показы. Сначала то,
 * что идёт вместе с самим событием (прямой эфир — первым, потом
 * предпочитаемые языки), дальше повторы по времени.
 *
 * Адрес потока содержит логин Xtream: он нужен только странице просмотра и
 * не попадает в ресурсы (BroadcastResource его не отдаёт).
 */
final readonly class EventBroadcasts
{
    /** Конец события неизвестен — считаем, что оно идёт столько. */
    private const int DEFAULT_HOURS = 2;

    /**
     * @param  list<string>  $preferredLanguages
     */
    public function __construct(
        private XtreamClient $client,
        private string $provider,
        private array $preferredLanguages,
    ) {}

    /**
     * @return list<Broadcast>
     */
    public function for(Event $event): array
    {
        $startsAt = $event->starts_at !== null ? CarbonImmutable::instance($event->starts_at) : null;
        $endsAt = $event->ends_at !== null ? CarbonImmutable::instance($event->ends_at) : $startsAt?->addHours(self::DEFAULT_HOURS);

        $airings = $event->airings()
            ->with('channel')
            ->where('ends_at', '>', CarbonImmutable::now())
            ->get()
            ->sortBy([
                fn (Airing $a, Airing $b) => $this->overlaps($b, $startsAt, $endsAt) <=> $this->overlaps($a, $startsAt, $endsAt),
                fn (Airing $a, Airing $b) => $b->live <=> $a->live,
                fn (Airing $a, Airing $b) => $this->languageRank($a->channel->language) <=> $this->languageRank($b->channel->language),
                fn (Airing $a, Airing $b) => $a->starts_at <=> $b->starts_at,
            ]);

        return array_values($airings->map(fn (Airing $airing) => new Broadcast(
            id: $airing->id,
            channel: $airing->channel->name,
            channelNumber: $airing->channel->number,
            country: null,
            language: $airing->channel->language,
            quality: null,
            provider: $this->provider,
            epgTitle: $airing->title,
            startsAt: CarbonImmutable::instance($airing->starts_at),
            endsAt: CarbonImmutable::instance($airing->ends_at),
            logo: $airing->channel->logo,
            live: $airing->live,
            replay: $airing->replay,
            streamUrl: $this->client->streamUrl($airing->channel->stream_id),
        ))->all());
    }

    private function overlaps(Airing $airing, ?CarbonImmutable $startsAt, ?CarbonImmutable $endsAt): bool
    {
        return $startsAt !== null && $endsAt !== null && $airing->starts_at->lt($endsAt) && $airing->ends_at->gt($startsAt);
    }

    private function languageRank(?string $language): int
    {
        $rank = $language !== null ? array_search($language, $this->preferredLanguages, true) : false;

        return $rank === false ? count($this->preferredLanguages) : $rank;
    }
}
