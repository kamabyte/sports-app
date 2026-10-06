<?php

use App\Guide\Data\Event;
use App\Guide\Data\EventStatus;
use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    // Вторник, вечер: идёт Лига чемпионов (19:00 UTC).
    $this->travelTo(CarbonImmutable::parse('2026-10-06 19:30:00', 'UTC'));
    $this->catalog = app(EventCatalog::class);
});

it('generates the same schedule for the same day', function (): void {
    $first = $this->catalog->events(new EventQuery(competition: 'ucl'))->map(fn (Event $event) => $event->title);
    $second = app()->make(EventCatalog::class)->events(new EventQuery(competition: 'ucl'))->map(fn (Event $event) => $event->title);

    expect($first->all())->toBe($second->all())->and($first)->not->toBeEmpty();
});

it('finds an event by id, even outside the current window, and only on match days', function (): void {
    $event = $this->catalog->find('ucl-20261006-2');

    expect($event)->not->toBeNull()
        ->and($event->competition->key)->toBe('ucl')
        ->and($event->startsAt->toIso8601String())->toBe('2026-10-06T19:00:00+00:00')
        ->and($event->status())->toBe(EventStatus::Live)
        ->and($this->catalog->find('ucl-20261005-0'))->toBeNull()
        ->and($this->catalog->find('nope'))->toBeNull()
        ->and($this->catalog->find('ucl-20261006-99'))->toBeNull();
});

it('filters by sport, status and search', function (): void {
    $live = $this->catalog->events(new EventQuery(statuses: [EventStatus::Live]));
    $hockey = $this->catalog->events(new EventQuery(sport: 'hockey'));
    $zenit = $this->catalog->events(new EventQuery(search: 'зенит'));

    expect($live)->not->toBeEmpty()
        ->and($live->every(fn (Event $event) => $event->status() === EventStatus::Live))->toBeTrue()
        ->and($hockey->every(fn (Event $event) => $event->sport->key === 'hockey'))->toBeTrue()
        ->and($zenit)->not->toBeEmpty()
        ->and($zenit->every(fn (Event $event) => $event->matchesSearch('Зенит')))->toBeTrue();
});

it('lists Russian broadcasts first', function (): void {
    $events = $this->catalog->events(new EventQuery(competition: 'epl'));

    $events->each(function (Event $event): void {
        $languages = array_map(fn ($broadcast) => $broadcast->language, $event->broadcasts);
        $firstForeign = collect($languages)->search(fn (string $language) => $language !== 'ru');

        expect(count($event->broadcasts))->toBeGreaterThanOrEqual(2);
        if ($firstForeign !== false) {
            expect(array_slice($languages, $firstForeign))->not->toContain('ru');
        }
    });
});

it('shows a partial score while live and none before the start', function (): void {
    $live = $this->catalog->find('ucl-20261006-2');
    $later = $this->catalog->find('ucl-20261007-2');

    expect($live->score())->not->toBeNull()
        ->and($live->score()->home)->toBeLessThanOrEqual($live->finalScore->home)
        ->and($live->liveLabel())->toBe('30′')
        ->and($later->score())->toBeNull()
        ->and($later->liveLabel())->toBeNull();
});
