<?php

namespace App\Guide\Fake;

use App\Guide\Data\Broadcast;
use App\Guide\Data\Competition;
use App\Guide\Data\Event;
use App\Guide\Data\Score;
use App\Guide\Data\Sport;
use App\Guide\Data\Team;
use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Сгенерированное расписание для прототипа: неделя вокруг сегодняшнего дня
 * по сетке из Fixtures. Генерация детерминирована (сид — турнир + дата),
 * поэтому id события вида «epl-20261006-2» всегда разворачивается в то же
 * событие, даже вне текущего окна.
 */
final class FakeEventCatalog implements EventCatalog
{
    private const int DAYS_BEFORE = 1;

    private const int DAYS_AFTER = 6;

    private const int MIN_BROADCASTS = 2;

    private const int MAX_BROADCASTS = 5;

    /** @var array<string, list<Event>> */
    private array $days = [];

    /**
     * @param  list<string>  $preferredLanguages
     */
    public function __construct(
        private readonly string $provider,
        private readonly array $preferredLanguages,
    ) {}

    public function sports(): Collection
    {
        return collect(Fixtures::sports())
            ->map(fn (array $sport, string $key) => new Sport($key, $sport['name'], $sport['emoji'], $sport['color']))
            ->values();
    }

    public function competitions(?string $sport = null): Collection
    {
        return collect(Fixtures::competitions())
            ->filter(fn (array $competition) => $sport === null || $competition['sport'] === $sport)
            ->map(fn (array $competition, string $key) => $this->competition($key, $competition))
            ->sortByDesc('weight')
            ->values();
    }

    public function events(EventQuery $query): Collection
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $from = $query->from ?? $today->subDays(self::DAYS_BEFORE);
        $to = $query->to ?? $today->addDays(self::DAYS_AFTER)->endOfDay();

        $competitions = collect(Fixtures::competitions())
            ->filter(fn (array $competition) => $query->sport === null || $competition['sport'] === $query->sport)
            ->filter(fn (array $competition, string $key) => $query->competition === null || $key === $query->competition);

        $events = collect();
        // Сетка — в UTC, а день зрителя может захватывать соседние сутки.
        for ($date = $from->utc()->startOfDay(); $date->lte($to); $date = $date->addDay()) {
            foreach ($competitions as $key => $competition) {
                $events->push(...$this->day($key, $competition, $date));
            }
        }

        $events = $events
            ->filter(fn (Event $event) => $event->endsAt->gt($from) && $event->startsAt->lte($to))
            ->filter(fn (Event $event) => $query->statuses === [] || in_array($event->status(), $query->statuses, true))
            ->filter(fn (Event $event) => $query->search === null || $event->matchesSearch($query->search))
            // По времени, в одно время — сначала большие турниры.
            ->sort(fn (Event $a, Event $b) => [$a->startsAt, $b->competition->weight] <=> [$b->startsAt, $a->competition->weight])
            ->values();

        if ($query->limit !== null) {
            return $events->take($query->limit)->values();
        }

        return $events;
    }

    public function find(string $id): ?Event
    {
        if (! preg_match('/^([a-z0-9]+)-(\d{8})-(\d+)$/', $id, $matches)) {
            return null;
        }

        [, $key, $date, $index] = $matches;
        $competition = Fixtures::competitions()[$key] ?? null;
        $day = CarbonImmutable::createFromFormat('!Ymd', $date, 'UTC');

        if ($competition === null || $day === null) {
            return null;
        }

        return $this->day($key, $competition, $day)[(int) $index] ?? null;
    }

    /**
     * @param  array<string, mixed>  $competition
     * @return list<Event>
     */
    private function day(string $key, array $competition, CarbonImmutable $date): array
    {
        return $this->days[$key.$date->format('Ymd')] ??= $this->generate($key, $competition, $date);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return list<Event>
     */
    private function generate(string $key, array $fixture, CarbonImmutable $date): array
    {
        $random = new Randomizer(new Mt19937(crc32($key.'|'.$date->format('Ymd'))));
        $sport = $this->sports()->firstWhere('key', $fixture['sport']);
        $competition = $this->competition($key, $fixture);
        $weekday = $date->dayOfWeekIso;

        $teams = array_map(
            fn (array $team) => new Team(...$team),
            $random->shuffleArray($fixture['teams'] ?? []),
        );

        // Сессии без пары на каждую: Формула-1 или кард UFC с одним главным боем.
        $slots = isset($fixture['sessions'])
            ? array_map(fn (array $session) => ['time' => $session[0], 'name' => $session[1], 'duration' => $session[2]], $fixture['sessions'][$weekday] ?? [])
            : array_map(fn (string $time) => ['time' => $time, 'name' => null, 'duration' => $fixture['duration']], $fixture['schedule'][$weekday] ?? []);

        $events = [];
        foreach ($slots as $index => $slot) {
            [$home, $away] = match (true) {
                $teams === [] => [null, null],
                isset($fixture['sessions']) => [$teams[0], $teams[1]],
                default => [$teams[(2 * $index) % count($teams)], $teams[(2 * $index + 1) % count($teams)]],
            };

            $startsAt = $date->setTimeFromTimeString($slot['time']);
            $title = $this->title($fixture, $slot['name'], $home, $away);

            $events[] = new Event(
                id: "{$key}-{$date->format('Ymd')}-{$index}",
                sport: $sport,
                competition: $competition,
                title: $title,
                home: $home,
                away: $away,
                round: $slot['name'] ?? $this->round($fixture['round'] ?? null, $date),
                venue: $fixture['venue'] ?? null,
                startsAt: $startsAt,
                endsAt: $startsAt->addMinutes($slot['duration']),
                broadcasts: $this->broadcasts($random, $fixture, $sport, $competition, $title, $startsAt, $startsAt->addMinutes($slot['duration'])),
                finalScore: $this->finalScore($random, $sport->key),
            );
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function competition(string $key, array $fixture): Competition
    {
        return new Competition($key, $fixture['sport'], $fixture['name'], $fixture['short'], $fixture['country'], $fixture['weight']);
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function title(array $fixture, ?string $session, ?Team $home, ?Team $away): string
    {
        return match (true) {
            $fixture['sport'] === 'mma' => "UFC Fight Night: {$home?->name} — {$away?->name}",
            $home !== null && $away !== null => "{$home->name} — {$away->name}",
            default => "{$fixture['title']} · {$session}",
        };
    }

    private function round(?string $type, CarbonImmutable $date): ?string
    {
        $season = CarbonImmutable::create($date->month >= 8 ? $date->year : $date->year - 1, 8, 15, 0, 0, 0, 'UTC');

        return match ($type) {
            'matchday' => (intdiv((int) $season->diffInDays($date), 7) + 1).'-й тур',
            'league-phase' => 'Общий этап, '.min(8, max(1, intdiv((int) $season->addMonth()->diffInDays($date), 14) + 1)).'-й тур',
            'regular' => 'Регулярный чемпионат',
            'tennis' => match ($date->dayOfWeekIso) {
                1, 2 => '2-й круг',
                3 => '3-й круг',
                4 => '1/8 финала',
                5 => '1/4 финала',
                6 => '1/2 финала',
                default => 'Финал',
            },
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return list<Broadcast>
     */
    private function broadcasts(Randomizer $random, array $fixture, Sport $sport, Competition $competition, string $title, CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $pool = $fixture['channels'];
        $count = $random->getInt(min(self::MIN_BROADCASTS, count($pool)), min(self::MAX_BROADCASTS, count($pool)));
        $channels = array_slice($random->shuffleArray($pool), 0, $count);

        // Сначала предпочитаемые языки, внутри языка — лучшее качество.
        $sorted = array_map(fn (string $key) => Fixtures::channels()[$key], $channels);
        usort($sorted, fn (array $a, array $b) => [$this->languageRank($a[3]), $this->qualityRank($b[4])]
            <=> [$this->languageRank($b[3]), $this->qualityRank($a[4])]);

        return array_map(
            fn (array $channel, int $index) => new Broadcast(
                id: $index + 1,
                channel: $channel[0],
                channelNumber: $channel[1],
                country: $channel[2],
                language: $channel[3],
                quality: $channel[4],
                provider: $this->provider,
                epgTitle: "{$sport->name}. {$competition->name}. {$title}. Прямая трансляция",
                startsAt: $startsAt,
                endsAt: $endsAt,
                live: true,
            ),
            $sorted,
            array_keys($sorted),
        );
    }

    private function finalScore(Randomizer $random, string $sport): ?Score
    {
        return match ($sport) {
            'football' => new Score($random->getInt(0, 4), $random->getInt(0, 3)),
            'hockey' => new Score($random->getInt(0, 6), $random->getInt(0, 5)),
            'basketball' => new Score($random->getInt(88, 128), $random->getInt(85, 125)),
            // По сетам: победитель берёт два.
            'tennis' => $random->getInt(0, 1) === 0
                ? new Score(2, $random->getInt(0, 1))
                : new Score($random->getInt(0, 1), 2),
            default => null,
        };
    }

    private function languageRank(string $language): int
    {
        $rank = array_search($language, $this->preferredLanguages, true);

        return $rank === false ? count($this->preferredLanguages) : $rank;
    }

    private function qualityRank(string $quality): int
    {
        return match ($quality) {
            '4K' => 3,
            'FHD' => 2,
            default => 1,
        };
    }
}
