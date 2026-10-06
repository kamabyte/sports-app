<?php

namespace App\Guide;

use App\Guide\Data\Broadcast;
use App\Guide\Data\Competition;
use App\Guide\Data\Event;
use App\Guide\Data\EventStatus;
use App\Guide\Data\Score;
use App\Guide\Data\Sport;
use App\Guide\Dispatcharr\XtreamClient;
use App\Models\Airing;
use App\Models\Competition as CompetitionModel;
use App\Models\Fixture;
use App\Models\SportEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * События, собранные из телегида (guide:import). Время — UTC в базе.
 */
final readonly class DatabaseEventCatalog implements EventCatalog
{
    private const int COMPETITIONS_LIMIT = 24;

    public function __construct(
        private XtreamClient $client,
        private string $provider,
        /** @var list<string> */
        private array $preferredLanguages,
    ) {}

    public function sports(): Collection
    {
        $present = SportEvent::query()
            ->where('ends_at', '>', now())
            ->whereHas('airings')
            ->distinct()
            ->pluck('sport')
            ->all();

        return SportRegistry::all()
            ->filter(fn (Sport $sport) => in_array($sport->key, $present, true))
            ->values();
    }

    public function competitions(?string $sport = null): Collection
    {
        $rows = SportEvent::query()
            ->selectRaw('competition_key, sport, max(competition) as competition, max(country) as country, max(weight) as weight, count(*) as events')
            ->whereNotNull('competition_key')
            ->where('ends_at', '>', now())
            ->whereHas('airings')
            ->when($sport, fn (Builder $query) => $query->where('sport', $sport))
            ->groupBy('competition_key', 'sport')
            ->orderByDesc('weight')
            ->orderByDesc('events')
            ->limit(self::COMPETITIONS_LIMIT)
            ->get();

        $catalog = CompetitionModel::query()->whereIn('key', $rows->pluck('competition_key'))->get()->keyBy('key');

        return $rows->map(function (SportEvent $row) use ($catalog): Competition {
            $model = $catalog->get((string) $row->competition_key);

            return $model !== null
                ? new Competition($model->key, $model->sport, $model->name, $model->short_name, $model->country, $model->weight, $model->logoUrl())
                : new Competition((string) $row->competition_key, $row->sport, (string) $row->competition, (string) $row->competition, $row->country, (int) $row->weight);
        });
    }

    public function events(EventQuery $query): Collection
    {
        $now = now();

        $events = SportEvent::query()
            ->with(['airings.channel', 'fixture', 'homeTeam', 'awayTeam', 'competitionModel'])
            // Событие — матч из расписания, который показывают на ваших каналах.
            ->whereHas('airings')
            ->when($query->sport, fn (Builder $builder, string $sport) => $builder->where('sport', $sport))
            ->when($query->competition, fn (Builder $builder, string $key) => $builder->where('competition_key', $key))
            ->when($query->from, fn (Builder $builder, CarbonImmutable $from) => $builder->where('ends_at', '>', $from))
            ->when($query->to, fn (Builder $builder, CarbonImmutable $to) => $builder->where('starts_at', '<=', $to))
            ->when($query->search, fn (Builder $builder, string $search) => $builder->where('search', 'like', '%'.mb_strtolower($search).'%'))
            ->when($query->statuses !== [], fn (Builder $builder) => $builder->where(function (Builder $where) use ($query, $now): void {
                foreach ($query->statuses as $status) {
                    match ($status) {
                        EventStatus::Upcoming => $where->orWhere('starts_at', '>', $now),
                        EventStatus::Live => $where->orWhere(fn (Builder $live) => $live->where('starts_at', '<=', $now)->where('ends_at', '>', $now)),
                        EventStatus::Finished => $where->orWhere('ends_at', '<=', $now),
                    };
                }
            }))
            ->orderBy('starts_at')
            ->orderByDesc('weight')
            ->orderBy('id')
            ->when($query->limit, fn (Builder $builder, int $limit) => $builder->limit($limit))
            ->get();

        return $events->map(fn (SportEvent $event) => $this->toEvent($event));
    }

    public function find(string $id): ?Event
    {
        if (! ctype_digit($id)) {
            return null;
        }

        $event = SportEvent::query()->with(['airings.channel', 'fixture', 'homeTeam', 'awayTeam', 'competitionModel'])->find((int) $id);

        return $event ? $this->toEvent($event) : null;
    }

    private function toEvent(SportEvent $event): Event
    {
        $sport = SportRegistry::find($event->sport) ?? SportRegistry::find('other');
        $fixture = $event->fixture;
        $startsAt = CarbonImmutable::instance($event->starts_at);
        $endsAt = CarbonImmutable::instance($event->ends_at);

        $home = $event->home ?? $event->homeTeam?->name;
        $away = $event->away ?? $event->awayTeam?->name;

        return new Event(
            id: (string) $event->id,
            sport: $sport,
            competition: $this->competition($event, $sport),
            title: $event->title,
            // Название — из телегида (по-русски), эмблема — сохранённая у нас.
            home: $home !== null ? TeamStyle::team($home, $event->homeTeam?->logoUrl(), $event->homeTeam?->logo_source === 'flag') : null,
            away: $away !== null ? TeamStyle::team($away, $event->awayTeam?->logoUrl(), $event->awayTeam?->logo_source === 'flag') : null,
            round: $event->round,
            venue: null,
            startsAt: $startsAt,
            endsAt: $endsAt,
            broadcasts: $this->broadcasts($event, $startsAt, $endsAt),
            reportedScore: $this->score($fixture),
        );
    }

    /**
     * Турнир из каталога (имя, эмблема), иначе — текст из телегида.
     */
    private function competition(SportEvent $event, Sport $sport): Competition
    {
        $model = $event->competitionModel;

        if ($model !== null) {
            return new Competition($model->key, $model->sport, $model->name, $model->short_name, $model->country, $model->weight, $model->logoUrl());
        }

        return new Competition(
            $event->competition_key ?? $event->sport,
            $event->sport,
            $event->competition ?? $sport->name,
            $event->competition ?? $sport->name,
            $event->country,
            $event->weight,
        );
    }

    /**
     * Счёт — только начатого матча: у будущего источники отдают 0:0 или null.
     */
    private function score(?Fixture $fixture): ?Score
    {
        if ($fixture === null || $fixture->home_score === null || $fixture->away_score === null) {
            return null;
        }

        if (! in_array($fixture->status, [Fixture::LIVE, Fixture::FINISHED], true)) {
            return null;
        }

        return new Score($fixture->home_score, $fixture->away_score);
    }

    /**
     * Сначала то, что идёт вместе с основным показом (прямой эфир — первым,
     * потом предпочитаемые языки), дальше повторы по времени.
     *
     * @return list<Broadcast>
     */
    private function broadcasts(SportEvent $event, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $airings = $event->airings->sortBy([
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

    private function overlaps(Airing $airing, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $airing->starts_at->lt($endsAt) && $airing->ends_at->gt($startsAt);
    }

    private function languageRank(?string $language): int
    {
        $rank = $language !== null ? array_search($language, $this->preferredLanguages, true) : false;

        return $rank === false ? count($this->preferredLanguages) : $rank;
    }
}
