<?php

use App\Guide\EventCatalog;
use App\Guide\EventQuery;
use App\Models\Airing;
use App\Models\Channel;
use App\Models\SportEvent;
use App\Models\TeamAlias;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

// Расписание — источник событий, телегид только добавляет к ним показы.

beforeEach(function (): void {
    config(['sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
});

it('imports channels and attaches live, simulcast and replay airings to the API match', function (): void {
    $fixture = scheduleMatch('handball', 'CSKA Moscow', 'SGAU Saratov', '2026-10-06 16:30', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr();

    $this->artisan('guide:import')->assertSuccessful();

    $match = matchOf($fixture);
    $airings = $match->airings()->orderBy('starts_at')->get();

    expect(Channel::query()->count())->toBe(3)
        ->and(Channel::query()->where('stream_id', 101)->value('language'))->toBe('ru')
        // Два канала в прямом эфире и ночной повтор — всё у одного матча.
        ->and($airings)->toHaveCount(3)
        ->and($airings->pluck('live')->all())->toBe([true, true, false])
        ->and($airings->pluck('replay')->all())->toBe([false, false, true])
        // Матч называется по-русски, как в телегиде.
        ->and($match->fresh()->title)->toBe('ЦСКА — СГАУ-Саратов');
});

it('creates no events from the guide alone', function (): void {
    // В расписании регби нет — «Красный Яр — Динамо» из телегида не событие.
    fakeDispatcharr();

    $this->artisan('guide:import')->assertSuccessful();

    expect(SportEvent::query()->count())->toBe(0)
        ->and(Airing::query()->count())->toBe(0);
});

it('learns Russian spellings from full matches', function (): void {
    scheduleMatch('handball', 'CSKA Moscow', 'SGAU Saratov', '2026-10-06 16:30', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr();

    $this->artisan('guide:import')->assertSuccessful();

    expect(TeamAlias::query()->where('source', 'guide')->pluck('alias')->sort()->values()->all())->toBe(['сгау саратов', 'цска']);
});

it('keeps match ids between imports and drops airings of removed channels', function (): void {
    $fixture = scheduleMatch('handball', 'CSKA Moscow', 'SGAU Saratov', '2026-10-06 16:30', ['source' => 'livescore']);
    buildMatches();
    $id = matchOf($fixture)->id;
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();

    buildMatches();
    fakeDispatcharr(streams: [1, 3]);
    $this->artisan('guide:import')->assertSuccessful();

    expect(matchOf($fixture)->id)->toBe($id)
        ->and(Channel::query()->pluck('stream_id')->sort()->values()->all())->toBe([101, 103])
        ->and(Airing::query()->count())->toBe(1);
});

it('fails without touching data when Dispatcharr returns nothing', function (): void {
    scheduleMatch('handball', 'CSKA Moscow', 'SGAU Saratov', '2026-10-06 16:30', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();

    resetHttpFakes();
    Http::fake(['dispatcharr.test/*' => Http::response([])]);
    $this->artisan('guide:import')->assertFailed();

    expect(Channel::query()->count())->toBe(3)->and(Airing::query()->count())->toBe(3);
});

it('serves matches with broadcasts through the catalog, web and API', function (): void {
    $fixture = scheduleMatch('handball', 'CSKA Moscow', 'SGAU Saratov', '2026-10-06 16:30', ['source' => 'livescore', 'status' => 'live', 'home_score' => 14, 'away_score' => 12]);
    // Матч без показов в телегиде — не событие.
    scheduleMatch('handball', 'Zenit', 'Chekhovskiye Medvedi', '2026-10-06 18:00', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $this->withoutVite();

    $catalog = app(EventCatalog::class);
    $event = $catalog->find((string) matchOf($fixture)->id);

    expect($event->status()->value)->toBe('live')
        ->and($event->score()?->home)->toBe(14)
        ->and($event->broadcasts[0]->live)->toBeTrue()
        ->and(collect($event->broadcasts)->last()->replay)->toBeTrue()
        ->and($event->broadcasts[0]->streamUrl)->toBe('http://dispatcharr.test/live/tester/secret/101?output_format=ts')
        ->and($catalog->events(new EventQuery)->pluck('title')->all())->toBe(['ЦСКА — СГАУ-Саратов'])
        ->and($catalog->sports()->pluck('key')->all())->toBe(['handball']);

    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('home')
        ->where('live.0.title', 'ЦСКА — СГАУ-Саратов'));

    $this->actingAs($user)->get("/events/{$event->id}/watch")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('watch')
        ->where('streamUrl', 'http://dispatcharr.test/live/tester/secret/101?output_format=ts')
        ->missing('event.broadcasts.0.stream_url'));

    $broadcast = $event->broadcasts[0]->id;
    $this->actingAs($user)->get("/events/{$event->id}/watch/{$broadcast}/playlist.m3u")
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/x-mpegurl; charset=utf-8')
        ->assertSee('http://dispatcharr.test/live/tester/secret/101?output_format=ts', escape: false);

    $this->actingAs($user, 'sanctum')->getJson("/api/events/{$event->id}/broadcasts/{$broadcast}/stream")
        ->assertOk()
        ->assertJsonPath('stream.type', 'mpegts');

    $this->actingAs($user, 'sanctum')->getJson('/api/events?q='.rawurlencode('цска'))
        ->assertOk()
        ->assertJsonPath('data.0.title', 'ЦСКА — СГАУ-Саратов')
        ->assertJsonMissingPath('data.0.broadcasts.0.stream_url');
});
