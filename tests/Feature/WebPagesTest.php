<?php

use App\Http\Middleware\DeferBroadcastPages;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 19:30:00', 'UTC'));
});

it('sends guests to the login page', function (string $url): void {
    $this->get($url)->assertRedirect('/login');
})->with(['/', '/schedule', '/sports/football', '/events/ucl-20261006-2', '/search?q=зенит']);

it('shows the home shelves', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('featured')
            ->has('live.0.channels')
            ->has('sportShelves.0.sport.emoji')
            ->has('sports', 6)
            ->where('timezone', 'Europe/Moscow'));
});

it('shows the schedule of a day in the viewer time zone', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/schedule?date=2026-10-07&sport=football')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('schedule')
            ->where('date', '2026-10-07')
            ->where('sport', 'football')
            ->has('days', 8)
            ->where('events', fn ($events) => collect($events)->every(fn (array $event) => $event['sport']['key'] === 'football')
                && collect($events)->every(fn (array $event) => CarbonImmutable::parse($event['starts_at'])->setTimezone('Europe/Moscow')->toDateString() === '2026-10-07')));
});

it('preserves event data and the deferred watch page', function (): void {
    $this->withoutMiddleware(DeferBroadcastPages::class);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/events/ucl-20261006-2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('event')
            ->where('event.status', 'live')
            ->has('event.broadcasts.0.channel')
            ->has('related'));

    $this->actingAs($user)
        ->get('/events/ucl-20261006-2/watch/2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('watch')->where('broadcastId', 2));
});

it('returns 404 for unknown events, broadcasts and sports', function (string $url): void {
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
})->with(['/events/ucl-20261006-99', '/sports/curling']);

it('survives a malformed search query', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/search?q=%D0')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('search'));
});

it('searches events by team', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/search?q='.rawurlencode('Зенит'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('search')
            ->where('query', 'Зенит')
            ->has('events.0'));
});

it('redirects deferred broadcast pages to the sports schedule', function (string $url): void {
    $this->actingAs(User::factory()->create())->get($url)->assertRedirect('/schedule');
})->with([
    '/channels',
    '/events/ucl-20261006-2/watch',
    '/events/ucl-20261006-2/watch/2',
    '/events/ucl-20261006-2/watch/2/playlist.m3u',
]);
