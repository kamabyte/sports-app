<?php

use App\Models\Airing as LegacyAiring;
use App\Models\User;
use App\Sports\Catalog\CatalogWriter;
use App\Sports\Models\Airing;
use App\Sports\Models\Event;
use App\Sports\Models\Name;
use Carbon\CarbonImmutable;
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
 * Гандбол ЦСКА — СГАУ-Саратов (в телегиде: прямой эфир на двух каналах и
 * повтор), матч КХЛ для слота «Чемпионат КХЛ», теннис и футбол без показов.
 *
 * @return array<string, Event>
 */
function seedBroadcastCatalog(): array
{
    $w = new CatalogWriter;
    $w->syncSports();
    $flashscore = $w->provider('flashscore', 'Flashscore');
    $russia = $w->country('russia', 'Russia', ['iso2' => 'RU']);
    $match = function (string $sport, string $path, string $competition, array $sides, string $startsAt) use ($w, $flashscore, $russia): Event {
        $sport = $w->sport($sport);
        $sport->update(['active' => true]);
        $category = $w->category($sport, 'country', 'russia-'.$sport->key, 'Russia', country: $russia);
        $competition = $w->competition($sport, $category, ['name' => $competition, 'participant_kind' => $sport->key === 'tennis' ? 'individual' : 'club'], [$flashscore, 'path', $path]);
        $start = CarbonImmutable::parse($startsAt, 'UTC');
        $event = $w->event($competition, ['kind' => 'match', 'status' => 'scheduled', 'starts_at' => $start, 'date' => $start->toDateString(), 'time_precision' => 'exact']);
        $entries = [];
        foreach ($sides as $index => [$name, $russian]) {
            if ($sport->key === 'tennis') {
                $athlete = $w->athlete($sport, ['name' => $name]);
                $w->name($athlete, 'ru', $russian);
                $entries[] = ['side' => $index + 1, 'entry' => $w->athleteEntry($sport, $athlete)];
            } else {
                $team = $w->team($sport, ['name' => $name, 'kind' => 'club', 'gender' => 'men', 'age_group' => 'senior']);
                $w->name($team, 'ru', $russian);
                $entries[] = ['side' => $index + 1, 'entry' => $w->teamEntry($team)];
            }
        }
        $w->eventEntries($event, $entries);

        return $event;
    };

    return [
        'handball' => $match('handball', '/handball/russia/superleague/', 'Superleague', [['CSKA Moscow', 'ЦСКА'], ['SGAU Saratov', 'СГАУ-Саратов']], '2026-10-06 16:30'),
        'khl' => $match('hockey', '/hockey/russia/khl/', 'KHL', [['SKA St. Petersburg', 'СКА'], ['Cherepovets', 'Северсталь']], '2026-10-06 16:30'),
        'tennis' => $match('tennis', '/tennis/atp-singles/shanghai/', 'Shanghai', [['Medvedev D.', 'Медведев Д.'], ['Sinner J.', 'Синнер Я.']], '2026-10-07 07:00'),
        'football' => $match('football', '/football/russia/premier-league/', 'Premier League', [['Zenit', 'Зенит'], ['Spartak Moscow', 'Спартак']], '2026-10-06 18:00'),
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
