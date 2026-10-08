<?php

namespace App\Sports\Catalog;

use App\Http\Resources\Catalog\CompetitionItem;
use App\Http\Resources\Catalog\EventItem;
use App\Sports\Models\Event;
use Illuminate\Database\Eloquent\Collection;

/**
 * Список событий под заголовками турниров: «Англия · Премьер-лига · 2026/2027».
 * Порядок групп — вид спорта, раздел, важность турнира, название; события
 * этапа (сессии Гран-при, бои турнира) — отдельной группой с его названием.
 */
final class EventGroups
{
    /**
     * @param  Collection<int, Event>  $events
     * @return list<array<string, mixed>>
     */
    public static function byCompetition(Collection $events): array
    {
        return array_values($events
            ->groupBy(fn (Event $event) => $event->competition_id.':'.($event->parent_id ?? 0))
            ->sortBy(fn ($group) => self::order($group->first()))
            ->map(function ($group) {
                /** @var Event $first */
                $first = $group->first();

                return [
                    'key' => $first->competition_id.':'.($first->parent_id ?? 0),
                    'competition' => CompetitionItem::make($first->competition)->resolve(),
                    'edition' => $first->edition?->label,
                    'parent' => $first->parent !== null ? ['name' => $first->parent->name_local ?? $first->parent->name, 'url' => Display::eventUrl($first->parent->id)] : null,
                    'events' => EventItem::collection($group->values())->resolve(),
                ];
            })
            ->values()
            ->all());
    }

    private static function order(Event $event): string
    {
        $competition = $event->competition;

        return sprintf(
            '%03d-%04d-%04d-%s-%s',
            $competition->sport->position,
            $competition->primaryCategory->position,
            $competition->position,
            mb_strtolower((string) Display::name($competition->primaryCategory)),
            mb_strtolower((string) Display::name($competition)),
        );
    }
}
