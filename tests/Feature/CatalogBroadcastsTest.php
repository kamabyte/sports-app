<?php

use App\Models\Airing as LegacyAiring;
use App\Models\Channel;
use App\Models\User;
use App\Sports\Catalog\CatalogWriter;
use App\Sports\Catalog\EventFeed;
use App\Sports\Models\Airing;
use App\Sports\Models\Event;
use App\Sports\Models\Name;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Трансляции в каталоге: телегид только добавляет показы к событиям
 * каталога, ничего в каталоге не меняет и не прячет события без показов.
 * Передачи — из fakeDispatcharr() (настоящие названия телегидов).
 */

beforeEach(function (): void {
    config(['sports.mode' => 'standalone', 'sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
});

/**
 * Матч каталога со страницей турнира Flashscore (path) и названиями сторон
 * по-английски и по-русски. Теннис — спортсмены, остальное — клубы.
 *
 * @param  list<array{0: string, 1: string}>  $sides
 */
function seedCatalogMatch(string $sport, string $path, string $competition, array $sides, string $startsAt, string $kind = 'club'): Event
{
    $w = new CatalogWriter;
    $w->syncSports();
    $flashscore = $w->provider('flashscore', 'Flashscore');
    $sport = $w->sport($sport);
    $sport->update(['active' => true]);
    $category = $w->category($sport, 'series', 'category-'.$sport->key, 'Category');
    $competition = $w->competition($sport, $category, ['name' => $competition, 'participant_kind' => $sport->key === 'tennis' ? 'individual' : $kind], [$flashscore, 'path', $path]);
    $start = CarbonImmutable::parse($startsAt, 'UTC');
    $event = $w->event($competition, ['kind' => 'match', 'status' => 'scheduled', 'starts_at' => $start, 'date' => $start->toDateString(), 'time_precision' => 'exact']);
    $entries = [];
    foreach ($sides as $index => [$name, $russian]) {
        if ($sport->key === 'tennis') {
            $athlete = $w->athlete($sport, ['name' => $name]);
            $w->name($athlete, 'ru', $russian);
            $entries[] = ['side' => $index + 1, 'entry' => $w->athleteEntry($sport, $athlete)];
        } else {
            $team = $w->team($sport, ['name' => $name, 'kind' => $kind, 'gender' => 'men', 'age_group' => 'senior']);
            $w->name($team, 'ru', $russian);
            $entries[] = ['side' => $index + 1, 'entry' => $w->teamEntry($team)];
        }
    }
    $w->eventEntries($event, $entries);

    return $event;
}

/**
 * Гандбол ЦСКА — СГАУ-Саратов (в телегиде: прямой эфир на двух каналах и
 * повтор), матч КХЛ для слота «Чемпионат КХЛ», теннис и футбол без показов.
 *
 * @return array<string, Event>
 */
function seedBroadcastCatalog(): array
{
    return [
        'handball' => seedCatalogMatch('handball', '/handball/russia/superleague/', 'Superleague', [['CSKA Moscow', 'ЦСКА'], ['SGAU Saratov', 'СГАУ-Саратов']], '2026-10-06 16:30'),
        'khl' => seedCatalogMatch('hockey', '/hockey/russia/khl/', 'KHL', [['SKA St. Petersburg', 'СКА'], ['Cherepovets', 'Северсталь']], '2026-10-06 16:30'),
        'tennis' => seedCatalogMatch('tennis', '/tennis/atp-singles/shanghai/', 'Shanghai', [['Medvedev D.', 'Медведев Д.'], ['Sinner J.', 'Синнер Я.']], '2026-10-07 07:00'),
        'football' => seedCatalogMatch('football', '/football/russia/premier-league/', 'Premier League', [['Zenit', 'Зенит'], ['Spartak Moscow', 'Спартак']], '2026-10-06 18:00'),
    ];
}

it('attaches live, simulcast and replay airings to catalog events', function (): void {
    $events = seedBroadcastCatalog();
    $names = Name::query()->count();
    fakeDispatcharr([
        ['1', '20261006162000', '20261006184000', 'Чемпионат КХЛ'],
        ['2', '20261007065500', '20261007090000', 'Теннис. ATP. Шанхай. Медведев – Синнер'],
    ]);

    $this->artisan('guide:import')->assertSuccessful();

    $handball = $events['handball']->airings()->orderBy('starts_at')->get();
    expect($handball->pluck('live')->all())->toBe([true, true, false])
        ->and($handball->pluck('replay')->all())->toBe([false, false, true])
        // Слот «Чемпионат КХЛ» — единственный матч турнира /hockey/russia/khl/ в эти минуты.
        ->and($events['khl']->airings()->pluck('title')->all())->toBe(['Чемпионат КХЛ'])
        // Спортсмены — по написаниям на любом языке.
        ->and($events['tennis']->airings()->count())->toBe(1)
        ->and($events['football']->airings()->count())->toBe(0)
        // «Красный Яр — Динамо» (регби) в каталоге нет — это не событие.
        ->and(Event::query()->count())->toBe(4)
        // Телегид ничего в каталоге не переименовывает и написаний не добавляет.
        ->and(Name::query()->count())->toBe($names)
        ->and(LegacyAiring::query()->count())->toBe(0);
});

it('does not guess a catalog slot with two games starting in it', function (): void {
    $events = seedBroadcastCatalog();
    $w = new CatalogWriter;
    $second = $w->event($events['khl']->competition, ['kind' => 'match', 'status' => 'scheduled', 'starts_at' => CarbonImmutable::parse('2026-10-06 16:30', 'UTC'), 'date' => '2026-10-06', 'time_precision' => 'exact']);
    $w->eventEntries($second, [['side' => 1, 'label' => 'Avangard Omsk', 'entry_status' => 'unlisted'], ['side' => 2, 'label' => 'Traktor', 'entry_status' => 'unlisted']]);
    fakeDispatcharr([['1', '20261006162000', '20261006184000', 'Чемпионат КХЛ']]);

    $this->artisan('guide:import')->assertSuccessful();

    expect(Airing::query()->where('title', 'Чемпионат КХЛ')->count())->toBe(0);
});

it('rebuilds future airings and drops those of removed channels', function (): void {
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();

    fakeDispatcharr(streams: [1, 3]);
    $this->artisan('guide:import')->assertSuccessful();

    // Остался один канал с телегидом: его прямой эфир, без повторов.
    expect($events['handball']->airings()->count())->toBe(1);
});

it('finds a pair without a sport word in every sport, but never guesses national teams', function (): void {
    // Setanta Sports 2, 11.10.2026: ни в названии, ни у канала вида спорта нет.
    $betis = seedCatalogMatch('football', '/football/spain/laliga/', 'LaLiga', [['Betis', 'Бетис'], ['Osasuna', 'Осасуна']], '2026-10-06 16:30');
    // FIFA+ и бейсбол: «Germany v Spain» без вида спорта может быть чем угодно.
    $baseball = seedCatalogMatch('baseball', '/baseball/world/premier-12/', 'Premier 12', [['Spain', 'Испания'], ['Germany', 'Германия']], '2026-10-06 16:30', 'national');
    fakeDispatcharr([
        ['1', '20261006162500', '20261006182500', '⋗ Испания. «Бетис» – «Осасуна»'],
        ['2', '20261006162500', '20261006182500', 'Germany v Spain - Semi-finals'],
    ]);

    $this->artisan('guide:import')->assertSuccessful();

    expect($betis->airings()->value('live'))->toBeTrue()
        ->and($baseball->airings()->count())->toBe(0);
});

it('does not take a show that ends before kickoff for the match', function (): void {
    $events = seedBroadcastCatalog();
    // Студия перед матчем с соперниками в названии: 16:00–16:25, матч в 16:30.
    fakeDispatcharr([['1', '20261006160000', '20261006162500', 'Гандбол. Перед игрой. ЦСКА – СГАУ-Саратов']]);

    $this->artisan('guide:import')->assertSuccessful();

    expect($events['handball']->airings()->where('starts_at', '<', CarbonImmutable::parse('2026-10-06 16:25', 'UTC'))->count())->toBe(0);
});

it('attaches a series game live, but not a replay it cannot tell apart', function (): void {
    // Star Sports 2, 11.10.2026: «2nd T20: India vs West Indies» — у серии те же соперники.
    $second = seedCatalogMatch('cricket', '/cricket/world/india-west-indies-t20/', 'T20 Series', [['India', 'Индия'], ['West Indies', 'Вест-Индия']], '2026-10-04 13:30');
    $third = seedCatalogMatch('cricket', '/cricket/world/india-west-indies-t20/', 'T20 Series', [['India', 'Индия'], ['West Indies', 'Вест-Индия']], '2026-10-06 13:30');
    fakeDispatcharr([
        ['1', '20261006132000', '20261006170000', 'Live - 3rd T20: India vs. West Indies; T20 Cricket'],
        ['2', '20261006050000', '20261006090000', '2nd T20: India vs West Indies; T20 Cricket'],
    ]);

    $this->artisan('guide:import')->assertSuccessful();

    expect($third->airings()->pluck('live')->all())->toBe([true])
        ->and($second->airings()->count())->toBe(0);
});

it('marks events on TV in lists, filters by it and keeps events without broadcasts', function (): void {
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $this->actingAs(User::factory()->create());

    $this->get('/schedule?date=2026-10-06')
        ->assertOk()
        ->assertDontSee('secret')
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/schedule')
            ->where('filters.tv', false)
            // События без показов в списке остаются — просто без отметки.
            ->where('groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['events'])->count() === 3
                && collect($groups)->flatMap(fn ($group) => $group['events'])->where('on_tv', true)->pluck('id')->all() === [$events['handball']->id]));

    $this->get('/schedule?date=2026-10-06&tv=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.tv', true)
            ->has('groups', 1)
            ->where('groups.0.events.0.id', $events['handball']->id)
            ->has('groups.0.events', 1));
});

it('shows broadcasts on the event page and the stream only on the watch page', function (): void {
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $this->actingAs(User::factory()->create());
    $id = $events['handball']->id;

    $this->get("/event/{$id}")
        ->assertOk()
        ->assertDontSee('secret')
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/event')
            ->has('broadcasts', 3)
            // Сначала показы во время матча, предпочитаемый язык (ru) — первым.
            ->where('broadcasts.0.live', true)
            ->where('broadcasts.0.language', 'ru')
            ->where('broadcasts.2.replay', true)
            ->missing('broadcasts.0.stream_url'));

    $broadcast = $events['handball']->airings()->orderBy('starts_at')->value('id');
    $this->get("/event/{$id}/watch/{$broadcast}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/watch')
            ->where('broadcastId', $broadcast)
            ->where('streamUrl', fn (string $url) => str_contains($url, '/live/tester/secret/'))
            ->where('playlistUrl', "/event/{$id}/watch/{$broadcast}/playlist.m3u"));

    $this->get("/event/{$id}/watch/{$broadcast}/playlist.m3u")
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/x-mpegurl; charset=utf-8');

    // Чужой показ и событие без трансляций — 404.
    $this->get("/event/{$events['football']->id}/watch")->assertNotFound();
});

it('preserves broadcast ids and watch links across guide refreshes', function (): void {
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $ids = $events['handball']->airings()->orderBy('id')->pluck('id')->all();
    $this->artisan('guide:import')->assertSuccessful();
    expect($events['handball']->airings()->orderBy('id')->pluck('id')->all())->toBe($ids);
});

it('keeps finished events hidden even with an upcoming repeat', function (): void {
    // Решение владельца (11.10.2026): каталог показывает только предстоящие события.
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $events['handball']->update(['status' => 'finished']);
    $this->actingAs(User::factory()->create());

    $this->get('/schedule?date=2026-10-07&tv=1')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('groups', 0)->where('next_date', null));
    $this->get('/event/'.$events['handball']->id)->assertNotFound();
    $this->get('/event/'.$events['handball']->id.'/watch')->assertNotFound();
});

it('preserves broadcasts for an unavailable guide while refreshing other channels', function (): void {
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $channel = Channel::query()->where('number', 1)->firstOrFail();
    $channel->update(['guide_source' => 'unavailable']);
    $ids = Airing::query()->where('channel_id', $channel->id)->pluck('id')->all();
    config(['sports.guides.sources.unavailable' => ['name' => 'Unavailable', 'url' => 'https://guide.test/down.xml']]);
    Http::fake(['guide.test/down.xml' => Http::response('Unavailable', 503)]);
    $this->artisan('guide:import')->assertSuccessful();
    expect(Airing::query()->where('channel_id', $channel->id)->pluck('id')->all())->toBe($ids)
        ->and($channel->fresh()->guide_source)->toBe('unavailable');
});

it('does not label an explicit repeat as live or attach it to a future kickoff', function (): void {
    $events = seedBroadcastCatalog();
    fakeDispatcharr([
        ['1', '20261006163500', '20261006183500', 'Гандбол. ЦСКА – СГАУ-Саратов. Повтор'],
        ['1', '20261006160000', '20261006180000', 'Гандбол. ЦСКА – СГАУ-Саратов. Повтор'],
    ]);
    $this->artisan('guide:import')->assertSuccessful();
    expect($events['handball']->airings()->where('title', 'like', '%Повтор')->count())->toBe(1)
        ->and($events['handball']->airings()->where('title', 'like', '%Повтор')->value('live'))->toBeFalse();
});

it('surfaces available broadcasts on the catalog homepage', function (): void {
    seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $this->actingAs(User::factory()->create())->get('/')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('broadcast_count', 1)->has('broadcast_groups', 1));
});

it('marks and filters by live broadcasts only, replays stay on the event page', function (): void {
    // Southampton — Portsmouth, 11.10.2026: прямые эфиры закончились, впереди только повторы.
    $events = seedBroadcastCatalog();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 18:10', 'UTC'));
    $this->actingAs(User::factory()->create());

    expect(EventFeed::day(CarbonImmutable::parse('2026-10-06', 'Europe/Moscow'), onTv: true)->count())->toBe(0)
        ->and(EventFeed::day(CarbonImmutable::parse('2026-10-07', 'Europe/Moscow'), onTv: true)->count())->toBe(0)
        ->and(EventFeed::query()->whereKey($events['handball']->id)->value('on_tv'))->toBeFalsy();
    $this->get('/event/'.$events['handball']->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('broadcasts', 1)->where('broadcasts.0.replay', true));
});
