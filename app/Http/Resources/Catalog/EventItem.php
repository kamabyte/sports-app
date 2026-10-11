<?php

namespace App\Http\Resources\Catalog;

use App\Sports\Catalog\Display;
use App\Sports\Models\Athlete;
use App\Sports\Models\Entry;
use App\Sports\Models\Event;
use App\Sports\Models\EventEntry;
use App\Sports\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Строка события в расписании: время, участники, турнир, отметка «по ТВ». Очная встреча —
 * две стороны; гонка или турнир — название и число участников. Счёта нет:
 * каталог показывает расписание. Неизвестный участник — «Уточняется», имя
 * из дневного списка — без ссылки (профиля пока нет).
 *
 * @mixin Event
 */
class EventItem extends JsonResource
{
    /** События с двумя сторонами. */
    public const array HEAD_TO_HEAD = ['match', 'bout', 'tie'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $competition = $this->competition;
        $broadcastStart = $this->getAttribute('broadcast_starts_at');
        $headToHead = in_array($this->kind, self::HEAD_TO_HEAD, true);

        return [
            'id' => $this->id,
            'url' => Display::eventUrl($this->id),
            'kind' => $this->kind,
            'family' => $competition->sport->family,
            'name' => $this->name_local ?? $this->name,
            'round' => $this->round_label_local ?? $this->round_label,
            'parent' => $this->parent !== null ? ['id' => $this->parent->id, 'name' => $this->parent->name_local ?? $this->parent->name, 'url' => Display::eventUrl($this->parent->id)] : null,
            'status' => $this->status,
            'status_label' => Display::status($this->status),
            'time_precision' => $this->time_precision,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'broadcast_starts_at' => is_string($broadcastStart) ? CarbonImmutable::parse($broadcastStart, 'UTC')->toIso8601String() : null,
            'date' => $this->date?->toDateString(),
            'competition' => CompetitionItem::make($competition)->resolve(),
            'sides' => $headToHead ? $this->sides() : null,
            'entrants' => $headToHead ? null : $this->entries_count ?? $this->entries->count(),
            // Есть трансляция, которая ещё не закончилась. Каналов и потоков в списках нет.
            'on_tv' => (bool) ($this->on_tv ?? false),
        ];
    }

    /**
     * Две стороны очной встречи (даже если соперник ещё неизвестен).
     *
     * @return list<array<string, mixed>>
     */
    private function sides(): array
    {
        $bySide = $this->entries->keyBy('side');

        return array_map(function (int $side) use ($bySide) {
            /** @var EventEntry|null $row */
            $row = $bySide->get($side);

            return [
                'side' => $side,
                'participant' => $row?->entry !== null ? self::participant($row->entry) : null,
                'label' => $row?->entry === null ? ($row->label ?? 'Уточняется') : null,
                // Имя из дневного списка: участник известен по имени, профиля пока нет.
                'provisional' => $row?->entry === null && $row?->entry_status === 'unlisted',
            ];
        }, [1, 2]);
    }

    /**
     * Участник: команда (с эмблемой или флагом сборной), спортсмен (с флагом),
     * пара или эстафета — с каждым спортсменом отдельной ссылкой.
     *
     * @return array<string, mixed>
     */
    public static function participant(Entry $entry): array
    {
        $team = $entry->team;
        $members = $entry->athletes->map(fn (Athlete $athlete) => self::member($athlete))->values()->all();

        return match ($entry->kind) {
            'team' => self::team($team),
            'individual' => ['kind' => 'individual'] + ($members[0] ?? ['name' => '—', 'url' => null, 'flag' => null]) + ['logo' => null, 'members' => $members, 'national' => false, 'code' => null],
            default => [
                'kind' => $entry->kind,
                'id' => $team?->id,
                'name' => $team !== null ? Display::name($team) : implode(' / ', array_column($members, 'name')),
                'url' => $team !== null ? Display::teamUrl($team) : null,
                'logo' => $team?->logo?->url,
                'flag' => null,
                'national' => $team?->kind === 'national',
                'code' => $team?->code,
                'members' => $members,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function team(?Team $team): array
    {
        return [
            'kind' => 'team',
            'id' => $team?->id,
            'name' => $team !== null ? Display::name($team) : '—',
            'url' => $team !== null ? Display::teamUrl($team) : null,
            'logo' => $team?->logo?->url,
            'flag' => null,
            'national' => $team?->kind === 'national',
            'code' => $team?->code,
            'members' => [],
        ];
    }

    /**
     * @return array{id: int, name: string, url: string, flag: string|null}
     */
    private static function member(Athlete $athlete): array
    {
        $country = $athlete->relationLoaded('nationalities') ? $athlete->nationalities->first()?->country : null;

        return [
            'id' => $athlete->id,
            'name' => (string) Display::name($athlete),
            'url' => Display::athleteUrl($athlete),
            'flag' => Display::flag($country),
        ];
    }
}
