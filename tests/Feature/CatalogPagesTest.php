<?php

use App\Models\User;
use App\Sports\Models\Athlete;
use App\Sports\Models\Competition;
use App\Sports\Models\Event;
use App\Sports\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Страницы каталога-расписания в самостоятельном режиме: только
 * предстоящие события, без счёта и истории.
 */

beforeEach(function () {
    config(['sports.mode' => 'standalone']);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 12:00', 'Europe/Moscow'));
    seedSchedule();
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('shows a football day under competition headings, without past matches', function () {
    $this->get('/sport/football')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/sport')
            ->where('filters.date', '2026-10-07')
            ->where('groups.0.competition.category.name', 'Англия')
            ->where('groups.0.competition.name', 'Премьер-лига')
            // Сегодня: Brighton — Everton (17:00) и Arsenal — Chelsea (19:30); вчерашний матч не показывается.
            ->has('groups.0.events', 2)
            ->has('panel.categories'));
});

it('starts the schedule today and ignores filters of another sport', function () {
    $tennis = Competition::query()->where('slug', 'shanghai')->sole();

    $this->get("/sport/football?date=2026-10-01&competition={$tennis->id}&category=atp-doubles")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.date', '2026-10-07')
            ->where('filters.competition', null)
            ->where('filters.category', null));
});

it('keeps catalog navigation on an empty day and offers the next day with events', function () {
    $this->get('/sport/football?date=2026-10-09')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('groups', [])->where('next_date', '2026-10-10')->has('panel.categories'));
});

it('walks Football → England → Premier League → match → team', function () {
    $this->get('/sport/football/england')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/category')
            ->where('crumbs.1.label', 'Англия')
            ->where('sections.0.competitions.0.name', 'Премьер-лига'));

    $this->get('/sport/football/england/premier-league')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/competition')
            ->has('fixtures', 3)
            ->where('more', false)
            ->missing('standings')
            ->missing('results'));

    $match = scheduleEvent('Premier League', 'Arsenal');
    $this->get("/event/{$match->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/event')
            ->where('crumbs.2.label', 'Премьер-лига')
            ->where('event.sides.0.participant.name', 'Арсенал')
            ->missing('event.sides.0.score'));

    $team = Team::query()->where('name', 'Arsenal')->sole();
    $this->get("/team/{$team->id}/{$team->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('catalog/team')->has('upcoming', 2)->where('team.badges', ['Клуб']));
});

it('tells senior, women, U21 and Olympic France apart', function () {
    $this->get('/sport/football/national-teams?country=france')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('teams', 4)
            ->where('teams.0.badges', ['Сборная', 'Мужчины'])
            ->where('teams.1.badges', ['Сборная', 'Мужчины', 'U21'])
            ->where('teams.2.badges', ['Сборная', 'Мужчины', 'Олимпийская'])
            ->where('teams.3.badges', ['Сборная', 'Женщины']));
});

it('shows tennis doubles as two clickable athletes per side', function () {
    $match = Event::query()->whereHas('competition', fn ($q) => $q->where('slug', 'shanghai'))->sole();

    $this->get("/event/{$match->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.family', 'racket')
            ->where('event.sides.0.participant.kind', 'pair')
            ->has('event.sides.0.participant.members', 2)
            ->where('event.sides.0.participant.members.0.url', fn (string $url) => str_starts_with($url, '/player/')));

    $medvedev = Athlete::query()->where('name', 'Medvedev D.')->sole();
    $this->get("/player/{$medvedev->id}/{$medvedev->slug}")
        ->assertOk()
        // Гражданство не показывается, пока источник его не дал.
        ->assertInertia(fn (Assert $page) => $page->where('athlete.country', null)->has('upcoming', 1)->has('partnerships', 1));
});

it('shows a race session with its entrants and the other sessions of the meeting', function () {
    $practice = Event::query()->where('name', 'Practice 1')->sole();

    $this->get("/event/{$practice->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.sides', null)
            ->has('event.start_list', 2)
            ->where('event.start_list.0.affiliation', 'Alpine')
            ->has('event.siblings', 2));
});

it('marks an unknown kickoff, an unknown opponent and a name from the day list honestly', function () {
    $tie = Event::query()->where('time_precision', 'date')->sole();
    $this->get("/event/{$tie->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.time_precision', 'date')
            ->where('event.sides.1.participant', null)
            ->where('event.sides.1.label', 'Победитель: Челси — Тоттенхэм')
            ->where('event.sides.1.provisional', false));

    $listed = Event::query()->whereHas('entries', fn ($q) => $q->where('label', 'Brighton'))->sole();
    $this->get("/event/{$listed->id}")
        ->assertInertia(fn (Assert $page) => $page->where('event.sides.0.label', 'Brighton')->where('event.sides.0.provisional', true));
});

it('searches English and Russian names and groups teams, national teams and players', function () {
    foreach (['Франция', 'France', 'fran'] as $query) {
        $this->get('/search?q='.rawurlencode($query))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.national_teams', fn ($teams) => collect($teams)->pluck('name')->contains('Франция') && collect($teams)->pluck('name')->contains('Франция (ж)')));
    }

    $this->get('/search?q=granollers')->assertInertia(fn (Assert $page) => $page->has('results.players', 1)->where('results.teams', []));
    $this->get('/search?q[]=x')->assertOk()->assertInertia(fn (Assert $page) => $page->where('query', ''));
});

it('marks sports without data instead of pretending to cover them', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/home')
            ->where('sports.0.key', 'football')
            ->where('sports.0.week', 4)
            ->where('catalog.sports', fn ($sports) => collect($sports)->firstWhere('key', 'darts')['has_data'] === false));

    $this->get('/sport/darts')->assertOk()->assertInertia(fn (Assert $page) => $page->where('sport.has_data', false)->where('groups', []));
});

it('sends old pages to the catalog in standalone mode', function () {
    $this->get('/sports/combat')->assertRedirect('/sport/mma');
    $this->get('/channels')->assertRedirect('/schedule');
});

it('works with no channels, airings or Dispatcharr at all', function () {
    Http::preventStrayRequests();
    Http::fake();

    foreach (['/', '/schedule', '/sport/tennis', '/search?q=arsenal', '/sport/football/england/premier-league'] as $url) {
        $this->get($url)->assertOk();
    }

    Http::assertNothingSent();
    expect(DB::table('channels')->count())->toBe(0)->and(DB::table('airings')->count())->toBe(0);
});
