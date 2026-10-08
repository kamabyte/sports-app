<?php

use App\Models\User;
use App\Sports\Catalog\CatalogWriter;
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

/** Время по Москве, дней от «сегодня». */
function at(int $days, string $time): CarbonImmutable
{
    return CarbonImmutable::parse(CarbonImmutable::now('Europe/Moscow')->addDays($days)->toDateString().' '.$time, 'Europe/Moscow')->utc();
}

/** Небольшое расписание: футбол (клубы и сборные), парный теннис, Гран-при. */
function seedSchedule(): void
{
    $w = new CatalogWriter;
    $w->syncSports();
    $match = function (Competition $competition, array $sides, int $days, ?string $time, string $status = 'scheduled') use ($w): Event {
        $start = at($days, $time ?? '00:00');
        $event = $w->event($competition, ['kind' => 'match', 'status' => $status, 'starts_at' => $start, 'date' => $start->setTimezone('Europe/Moscow')->toDateString(), 'time_precision' => $time === null ? 'date' : 'exact']);
        $w->eventEntries($event, $sides);

        return $event;
    };
    $side = fn (int $number, $entry, ?string $label = null, ?string $status = null) => ['side' => $number, 'entry' => $entry, 'label' => $label, 'entry_status' => $status];

    // Футбол.
    $football = $w->sport('football');
    $football->update(['active' => true]);
    $england = $w->country('england', 'England', ['kind' => 'sporting_territory', 'flag_url' => 'https://flagcdn.com/w80/gb-eng.png']);
    $france = $w->country('france', 'France', ['iso2' => 'FR']);
    $italy = $w->country('italy', 'Italy', ['iso2' => 'IT']);
    $w->name($england, 'ru', 'Англия', curated: true);
    $englandCategory = $w->category($football, 'country', 'england', 'England', country: $england, position: 1);
    $w->name($englandCategory, 'ru', 'Англия', curated: true);
    $europe = $w->category($football, 'region', 'europe', 'Europe', region: $w->region('europe', 'Europe', 'Европа'));
    $pl = $w->competition($football, $englandCategory, ['name' => 'Premier League', 'slug' => 'premier-league', 'kind' => 'league', 'participant_kind' => 'club', 'position' => 1]);
    $w->name($pl, 'ru', 'Премьер-лига', curated: true);
    $unl = $w->competition($football, $europe, ['name' => 'UEFA Nations League', 'slug' => 'uefa-nations-league', 'participant_kind' => 'national']);

    $team = function (string $name, string $russian, array $attributes = []) use ($w, $football) {
        $team = $w->team($football, ['name' => $name] + $attributes);
        $w->name($team, 'ru', $russian, curated: true);

        return $w->teamEntry($team);
    };
    $arsenal = $team('Arsenal', 'Арсенал', ['kind' => 'club']);
    $chelsea = $team('Chelsea', 'Челси', ['kind' => 'club']);
    $national = fn (string $name, string $russian, array $attributes) => $team($name, $russian, $attributes + ['kind' => 'national', 'country_id' => $france->id, 'gender' => 'men', 'age_group' => 'senior', 'squad' => 'first']);
    $frenchSenior = $national('France', 'Франция', []);
    $national('France W', 'Франция (ж)', ['gender' => 'women']);
    $national('France U21', 'Франция U21', ['age_group' => 'u21']);
    $national('France Ol.', 'Франция (олимп.)', ['squad' => 'olympic']);
    $italian = $team('Italy', 'Италия', ['kind' => 'national', 'country_id' => $italy->id, 'gender' => 'men', 'age_group' => 'senior']);

    $match($pl, [$side(1, $arsenal), $side(2, $chelsea)], 0, '19:30');
    $match($pl, [$side(1, $chelsea), $side(2, $arsenal)], -1, '19:30', 'finished');
    $match($pl, [$side(1, $arsenal), $side(2, null, 'Победитель: Челси — Тоттенхэм')], 1, null);
    $match($pl, [$side(1, null, 'Brighton', 'unlisted'), $side(2, null, 'Everton', 'unlisted')], 0, '17:00');
    $match($unl, [$side(1, $frenchSenior), $side(2, $italian)], 3, '21:45');

    // Теннис: пары — заявки из двух спортсменов.
    $tennis = $w->sport('tennis');
    $tennis->update(['active' => true]);
    $atp = $w->category($tennis, 'tour', 'atp', 'ATP', position: 1);
    $doubles = $w->category($tennis, 'tour', 'atp-doubles', 'ATP - Doubles', parent: $atp);
    $shanghai = $w->competition($tennis, $doubles, ['name' => 'Shanghai (China), hard', 'slug' => 'shanghai', 'participant_kind' => 'individual', 'entry_format' => 'doubles', 'gender' => 'men']);
    $player = function (string $name, ?string $country) use ($w, $tennis) {
        $athlete = $w->athlete($tennis, ['name' => $name]);
        if ($country !== null) {
            $w->nationality($athlete, $w->country(strtolower($country), $country), null);
        }

        return $athlete;
    };
    $granollers = $player('Granollers M.', 'Spain');
    $zeballos = $player('Zeballos H.', 'Argentina');
    $medvedev = $player('Medvedev D.', null);
    $krawietz = $player('Krawietz K.', 'Germany');
    $match($shanghai, [$side(1, $w->pairEntry($tennis, $granollers, $zeballos)), $side(2, $w->pairEntry($tennis, $medvedev, $krawietz))], 1, '12:00');

    // Формула-1: этап, сессии, пилоты и их команды.
    $motorsport = $w->sport('motorsport');
    $motorsport->update(['active' => true]);
    $f1 = $w->category($motorsport, 'series', 'auto-racing-formula-1', 'Formula 1');
    $gp = $w->competition($motorsport, $f1, ['name' => 'Singapore Grand Prix', 'slug' => 'singapore-grand-prix', 'participant_kind' => 'individual']);
    $meeting = $w->event($gp, ['kind' => 'meeting', 'name' => 'Singapore Grand Prix', 'starts_at' => at(0, '12:30'), 'date' => '2026-10-07', 'status' => 'scheduled']);
    $drivers = [[$w->athleteEntry($motorsport, $w->athlete($motorsport, ['name' => 'Gasly P.'])), 'Alpine'], [$w->athleteEntry($motorsport, $w->athlete($motorsport, ['name' => 'Verstappen M.'])), 'Red Bull']];
    foreach ([['Practice 1', 0, '12:30'], ['Race', 3, '15:00']] as [$name, $days, $time]) {
        $session = $w->event($gp, ['kind' => 'session', 'parent_id' => $meeting->id, 'name' => $name, 'starts_at' => at($days, $time), 'date' => at($days, $time)->setTimezone('Europe/Moscow')->toDateString(), 'status' => 'scheduled', 'venue' => 'Marina Bay Street Circuit']);
        $w->eventEntries($session, array_map(fn (array $driver) => ['entry' => $driver[0], 'affiliation' => $driver[1]], $drivers));
    }
}

function scheduleEvent(string $competition, string $home): Event
{
    return Event::query()
        ->whereHas('competition', fn ($q) => $q->where('name', $competition))
        ->whereHas('entries', fn ($q) => $q->where('side', 1)->whereHas('entry.team', fn ($t) => $t->where('name', $home)))
        ->where('status', 'scheduled')
        ->where('time_precision', 'exact')
        ->sole();
}

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
