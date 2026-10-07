<?php

use App\Http\Middleware\DeferBroadcastPages;
use App\Models\Channel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
    $this->withoutVite();

    // ЦСКА — СГАУ: прямой эфир на «Матч! Планете» и «Матч! Игре», повтор ночью на «Игре».
    scheduleMatch('handball', 'CSKA Moscow', 'SGAU Saratov', '2026-10-06 16:30', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $this->user = User::factory()->create();
});

it('does not load channels into shared page props', function (): void {
    $this->actingAs($this->user)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->missing('sidebarChannels'));
});

it('preserves the deferred channel listing', function (): void {
    $this->withoutMiddleware(DeferBroadcastPages::class);
    $this->actingAs($this->user)->get('/channels')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels')
        ->has('channels', 3)
        ->where('channels.0.now.title', 'ЦСКА — СГАУ-Саратов')
        ->where('channels.2.now', null));
});

it('preserves the deferred channel page', function (): void {
    $this->withoutMiddleware(DeferBroadcastPages::class);
    $channel = Channel::query()->where('name', 'Матч! Игра')->sole();

    $this->actingAs($this->user)->get("/channels/{$channel->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channel')
        ->where('streamUrl', 'http://dispatcharr.test/live/tester/secret/102?output_format=ts')
        ->has('schedule', 2)
        ->where('schedule.0.replay', false)
        ->where('schedule.1.replay', true)
        ->where('schedule.0.event.title', 'ЦСКА — СГАУ-Саратов'));
});

it('serves channels to TV clients', function (): void {
    $channel = Channel::query()->where('name', 'Матч! Игра')->sole();

    $this->actingAs($this->user, 'sanctum')->getJson('/api/channels')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Матч! Планета')
        ->assertJsonPath('data.0.now.title', 'ЦСКА — СГАУ-Саратов');

    $this->actingAs($this->user, 'sanctum')->getJson("/api/channels/{$channel->id}")
        ->assertOk()
        ->assertJsonPath('stream.type', 'mpegts')
        ->assertJsonPath('schedule.1.replay', true)
        ->assertJsonPath('schedule.0.event.home.name', 'ЦСКА');
});

it('requires login', function (): void {
    $this->get('/channels')->assertRedirect('/login');
    $this->getJson('/api/channels')->assertUnauthorized();
});

it('redirects an existing channel page while broadcasts are deferred', function (): void {
    $channel = Channel::query()->firstOrFail();
    $this->actingAs($this->user)->get("/channels/{$channel->id}")->assertRedirect('/schedule');
});
