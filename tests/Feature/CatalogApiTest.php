<?php

use App\Models\User;
use App\Sports\Models\Athlete;
use App\Sports\Models\Competition;
use App\Sports\Models\Team;
use Carbon\CarbonImmutable;

/*
 * API каталога для ТВ-клиентов (/api/v1, docs/api.md): те же данные, что у
 * веб-страниц, сущности — по id и ключам. Адрес потока — только в /stream.
 */

beforeEach(function (): void {
    config(['sports.mode' => 'standalone']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function signInApi(): void
{
    test()->actingAs(User::factory()->create(), 'sanctum');
}

it('needs a token and issues one per device', function (): void {
    $this->getJson('/api/v1/home')->assertUnauthorized();
    User::factory()->create(['email' => 'fan@example.com']);

    $token = $this->postJson('/api/v1/tokens', ['email' => 'fan@example.com', 'password' => 'password', 'device_name' => 'Apple TV'])
        ->assertCreated()
        ->json('token');

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.email', 'fan@example.com')
        ->assertJsonPath('timezone', 'Europe/Moscow')
        ->assertJsonPath('locale', 'ru');
    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

    expect(User::query()->sole()->tokens()->count())->toBe(0);
});

it('serves home, schedule and sport navigation like the web pages', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 12:00', 'Europe/Moscow'));
    seedSchedule();
    signInApi();

    $this->getJson('/api/v1/home')
        ->assertOk()
        ->assertJsonPath('date', '2026-10-07')
        ->assertJsonPath('sports.0.key', 'football')
        ->assertJsonStructure(['broadcast_groups', 'broadcast_count', 'sports' => [['key', 'name', 'week', 'day', 'groups' => [['competition', 'events']]]]]);

    $this->getJson('/api/v1/sports')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'football')
        ->assertJsonPath('data.0.has_data', true);

    // Прошедший матч не показывается; неизвестный соперник — подпись, а не выдумка.
    $day = $this->getJson('/api/v1/schedule?sport=football')
        ->assertOk()
        ->assertJsonPath('filters.sport', 'football')
        ->assertJsonPath('groups.0.competition.name', 'Премьер-лига')
        ->json('groups.0.events');
    expect(collect($day)->pluck('status')->unique()->all())->toBe(['scheduled'])
        ->and($day[0]['sides'][0]['participant'])->toBeNull()
        ->and($day[0]['sides'][0]['label'])->toBe('Brighton');

    // Раздел другого вида спорта не применяется; пустой день подсказывает ближайший.
    $this->getJson('/api/v1/schedule?sport=football&category=atp&date=2026-10-09')
        ->assertOk()
        ->assertJsonPath('filters.category', null)
        ->assertJsonPath('groups', [])
        ->assertJsonPath('next_date', '2026-10-10');
    $this->getJson('/api/v1/schedule?date=07.10.2026')->assertUnprocessable();

    $this->getJson('/api/v1/sports/football')
        ->assertOk()
        ->assertJsonPath('sport.key', 'football')
        ->assertJsonPath('has_national_teams', true)
        ->assertJsonStructure(['popular', 'categories' => [['key', 'name', 'children']]]);
    $this->getJson('/api/v1/sports/football/competitions')->assertOk()->assertJsonPath('sections.0.key', 'england');
    $this->getJson('/api/v1/sports/football/categories/england')
        ->assertOk()
        ->assertJsonPath('category.name', 'Англия')
        ->assertJsonPath('sections.0.title', 'Лиги')
        ->assertJsonPath('sections.0.competitions.0.name', 'Премьер-лига');
    $this->getJson('/api/v1/sports/football/national-teams?country=france')
        ->assertOk()
        ->assertJsonPath('country.code', 'france')
        ->assertJsonCount(4, 'teams')
        ->assertJsonPath('teams.0.name', 'Франция');
    $this->getJson('/api/v1/sports/tennis/players')->assertOk()->assertJsonCount(4, 'players')->assertJsonPath('players.0.name', 'Granollers M.');

    $this->getJson('/api/v1/sports/curling')->assertNotFound();
    $this->getJson('/api/v1/sports/football/categories/nowhere')->assertNotFound();
});

it('opens competitions, teams, players and events by id', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 12:00', 'Europe/Moscow'));
    seedSchedule();
    signInApi();

    $pl = Competition::query()->where('slug', 'premier-league')->sole();
    // limit не меньше шага порции (60): «ещё» — следующий шаг.
    $this->getJson("/api/v1/competitions/{$pl->id}?limit=1")
        ->assertOk()
        ->assertJsonPath('competition.name', 'Премьер-лига')
        ->assertJsonPath('competition.category.key', 'england')
        ->assertJsonCount(3, 'fixtures')
        ->assertJsonPath('limit', 60)
        ->assertJsonPath('more', false);

    $arsenal = Team::query()->where('name', 'Arsenal')->sole();
    $this->getJson("/api/v1/teams/{$arsenal->id}")
        ->assertOk()
        ->assertJsonPath('team.name', 'Арсенал')
        ->assertJsonPath('sport.key', 'football')
        ->assertJsonCount(2, 'upcoming')
        ->assertJsonPath('competitions.0.id', $pl->id);

    $granollers = Athlete::query()->where('name', 'Granollers M.')->sole();
    $this->getJson("/api/v1/players/{$granollers->id}")
        ->assertOk()
        ->assertJsonPath('athlete.country.name', 'Spain')
        ->assertJsonPath('partnerships.0.kind', 'pair')
        ->assertJsonPath('partnerships.0.partners.0.name', 'Zeballos H.');

    $event = scheduleEvent('Premier League', 'Arsenal');
    $this->getJson("/api/v1/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('event.id', $event->id)
        ->assertJsonPath('event.sides.0.participant.id', $arsenal->id)
        ->assertJsonPath('broadcasts', [])
        ->assertJsonPath('related_title', 'Ближайшие матчи турнира');

    $this->getJson('/api/v1/search?q='.rawurlencode('франц'))
        ->assertOk()
        ->assertJsonCount(4, 'results.national_teams')
        ->assertJsonPath('too_short', false);
    $this->getJson('/api/v1/search?q='.rawurlencode('ф'))->assertOk()->assertJsonPath('too_short', true);
    $this->getJson('/api/v1/search?q='.str_repeat('x', 101))->assertUnprocessable();

    $this->getJson('/api/v1/competitions/999999')->assertNotFound();
    $this->getJson('/api/v1/teams/999999')->assertNotFound();
    $this->getJson('/api/v1/players/999999')->assertNotFound();
    $this->getJson('/api/v1/events/999999')->assertNotFound();
});

it('lists broadcasts without stream URLs and gives the stream only on request', function (): void {
    config(['sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    signInApi();
    $id = $events['handball']->id;

    $this->getJson('/api/v1/schedule?tv=1&date=2026-10-06')
        ->assertOk()
        ->assertJsonPath('groups.0.events.0.id', $id)
        ->assertJsonPath('groups.0.events.0.on_tv', true)
        ->assertDontSee('secret');

    $this->getJson("/api/v1/events/{$id}")
        ->assertOk()
        ->assertDontSee('secret')
        ->assertJsonCount(3, 'broadcasts')
        ->assertJsonPath('broadcasts.0.live', true)
        ->assertJsonMissingPath('broadcasts.0.stream_url');

    $broadcast = $events['handball']->airings()->orderBy('starts_at')->value('id');
    $this->getJson("/api/v1/events/{$id}/broadcasts/{$broadcast}/stream")
        ->assertOk()
        ->assertJsonPath('broadcast.id', $broadcast)
        ->assertJsonPath('stream.type', 'mpegts')
        ->assertJsonPath('stream.url', fn (string $url) => str_contains($url, '/live/tester/secret/'));

    // Показ другого события — 404.
    $this->getJson("/api/v1/events/{$events['football']->id}/broadcasts/{$broadcast}/stream")->assertNotFound();
});

it('answers 410 on the legacy API in standalone mode', function (): void {
    signInApi();

    $this->getJson('/api/home')->assertStatus(410)->assertJsonPath('message', 'Use /api/v1 (catalog API).');
    $this->getJson('/api/events')->assertStatus(410);
});
