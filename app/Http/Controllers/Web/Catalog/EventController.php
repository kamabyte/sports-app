<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\EventDetail;
use App\Http\Resources\Catalog\EventItem;
use App\Sports\Catalog\Display;
use App\Sports\Catalog\EventFeed;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Событие: хлебные крошки до турнира, шапка с участниками и временем,
 * разбор результата под вид спорта и соседние события того же турнира.
 */
class EventController extends Controller
{
    use ResolvesCatalog;

    public function show(int $catalogEvent): Response
    {
        $event = EventFeed::query()->find($catalogEvent) ?? abort(404);
        $competition = $event->competition;
        $sport = $competition->sport;
        $crumbs = $this->crumbs($sport, $competition->primaryCategory, $competition);
        $parentName = $event->parent->name_local ?? $event->parent?->name;
        if ($event->parent !== null && $parentName !== Display::name($competition)) {
            $crumbs[] = ['label' => (string) $parentName, 'url' => Display::eventUrl($event->parent->id)];
        }

        return Inertia::render('catalog/event', [
            'event' => EventDetail::make($event)->resolve(),
            'sport' => ['key' => $sport->key, 'name' => $sport->name, 'family' => $sport->family, 'url' => Display::sportUrl($sport->key)],
            'crumbs' => $crumbs,
            'related' => EventItem::collection(EventFeed::related($event))->resolve(),
            'related_title' => $event->parent_id !== null ? 'Другие сессии: '.$parentName : 'Ближайшие матчи турнира',
        ]);
    }
}
