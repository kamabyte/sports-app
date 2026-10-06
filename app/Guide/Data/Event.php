<?php

namespace App\Guide\Data;

use Carbon\CarbonImmutable;

/**
 * Спортивное событие — то, что видит пользователь вместо списка каналов.
 * Матч (home/away) или событие без соперников (сессия Формулы-1).
 */
final readonly class Event
{
    /**
     * @param  list<Broadcast>  $broadcasts
     */
    public function __construct(
        public string $id,
        public Sport $sport,
        public Competition $competition,
        public string $title,
        public ?Team $home,
        public ?Team $away,
        public ?string $round,
        public ?string $venue,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public array $broadcasts,
        /** Итоговый счёт фейкового матча; в эфире показывается его часть по ходу матча. */
        public ?Score $finalScore = null,
        /** Счёт из расписания матчей — настоящий. */
        public ?Score $reportedScore = null,
    ) {}

    public function status(): EventStatus
    {
        $now = CarbonImmutable::now();

        return match (true) {
            $now->lt($this->startsAt) => EventStatus::Upcoming,
            $now->lt($this->endsAt) => EventStatus::Live,
            default => EventStatus::Finished,
        };
    }

    /**
     * Доля прошедшего времени трансляции: 0 — не началась, 1 — закончилась.
     */
    public function progress(): float
    {
        $total = $this->startsAt->diffInSeconds($this->endsAt);
        $elapsed = $this->startsAt->diffInSeconds(CarbonImmutable::now());

        return max(0.0, min(1.0, $total > 0 ? $elapsed / $total : 1.0));
    }

    public function score(): ?Score
    {
        if ($this->reportedScore !== null) {
            return $this->reportedScore;
        }

        if ($this->finalScore === null || $this->status() === EventStatus::Upcoming) {
            return null;
        }

        $progress = $this->progress();

        return new Score(
            (int) floor($this->finalScore->home * $progress),
            (int) floor($this->finalScore->away * $progress),
        );
    }

    /**
     * Что сейчас в матче: «67′», «2-й период», «3-й сет».
     */
    public function liveLabel(): ?string
    {
        // Без счёта нет и данных о ходе матча: минуту не выдумываем.
        if ($this->finalScore === null || $this->status() !== EventStatus::Live) {
            return null;
        }

        $progress = $this->progress();

        return match ($this->sport->key) {
            'football' => $this->footballMinute($progress),
            'hockey' => min(3, (int) floor($progress * 3) + 1).'-й период',
            'basketball' => min(4, (int) floor($progress * 4) + 1).'-я четверть',
            'tennis' => min(3, (int) floor($progress * 3) + 1).'-й сет',
            default => 'В эфире',
        };
    }

    /**
     * Все показы — записи: в витрину и «Сейчас в эфире» такое не ставим.
     */
    public function isReplayOnly(): bool
    {
        return $this->broadcasts !== [] && array_all($this->broadcasts, fn (Broadcast $broadcast) => $broadcast->replay);
    }

    public function matchesSearch(string $query): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $this->title,
            $this->competition->name,
            $this->competition->shortName,
            $this->sport->name,
            $this->home?->name,
            $this->away?->name,
        ])));

        return str_contains($haystack, mb_strtolower($query));
    }

    private function footballMinute(float $progress): string
    {
        // 115 минут эфира: 45 + перерыв 15 + 45 + добавленное.
        $minute = (int) floor($progress * 115);

        return match (true) {
            $minute <= 45 => max(1, $minute).'′',
            $minute < 60 => 'Перерыв',
            default => min(90, $minute - 15).'′',
        };
    }
}
