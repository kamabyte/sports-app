<?php

use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-06 19:30:00', 'UTC'));
});

it('requires a token', function (): void {
    $this->getJson('/api/home')->assertUnauthorized();
});

it('issues a token for a device and revokes it', function (): void {
    User::factory()->create(['email' => 'fan@example.com']);

    $token = $this->postJson('/api/tokens', [
        'email' => 'fan@example.com',
        'password' => 'password',
        'device_name' => 'Android TV',
    ])->assertCreated()->json('token');

    $this->withToken($token)->getJson('/api/home')->assertOk();
    $this->withToken($token)->deleteJson('/api/tokens/current')->assertNoContent();

    expect(User::query()->sole()->tokens()->count())->toBe(0);
});

it('rejects a wrong password', function (): void {
    User::factory()->create(['email' => 'fan@example.com']);

    $this->postJson('/api/tokens', ['email' => 'fan@example.com', 'password' => 'nope', 'device_name' => 'TV'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('serves home, sports, events and streams', function (): void {
    $this->actingAs(User::factory()->create(), 'sanctum');

    $this->getJson('/api/home')
        ->assertOk()
        ->assertJsonStructure(['featured', 'live' => [['id', 'title', 'status', 'broadcasts']], 'starting_soon', 'sports' => [['sport', 'events']]]);

    $this->getJson('/api/sports')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'football')
        ->assertJsonStructure(['data' => [['key', 'name', 'competitions']]]);

    $live = $this->getJson('/api/events?status=live&sport=football')->assertOk()->json('data');
    expect($live)->not->toBeEmpty()
        ->and(collect($live)->pluck('status')->unique()->all())->toBe(['live']);

    $this->getJson('/api/events?date=2026-10-08&q=лига')->assertOk();
    $this->getJson('/api/events?status=soon')->assertUnprocessable();
    $this->getJson('/api/events?q='.str_repeat('x', 101))->assertUnprocessable();

    $this->getJson('/api/events/ucl-20261006-2')
        ->assertOk()
        ->assertJsonPath('data.status', 'live')
        ->assertJsonPath('data.competition.key', 'ucl');

    $this->getJson('/api/events/ucl-20261006-2/broadcasts/1/stream')
        ->assertOk()
        ->assertJsonPath('stream.type', 'demo')
        ->assertJsonPath('broadcast.id', 1);

    $this->getJson('/api/events/ucl-20261006-2/broadcasts/42/stream')->assertNotFound();
    $this->getJson('/api/events/nope')->assertNotFound();
});
