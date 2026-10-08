<?php

use App\Sports\Catalog\EventFeed;
use App\Sports\Flashscore\PageFetcher;
use App\Sports\Models\Athlete;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Coverage;
use App\Sports\Models\Edition;
use App\Sports\Models\Entry;
use App\Sports\Models\Event;
use App\Sports\Models\ImportRun;
use App\Sports\Models\Name;
use App\Sports\Models\Observation;
use App\Sports\Models\Sport;
use App\Sports\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * Импорт каталога со страниц Flashscore: образцы — уменьшенные копии
 * настоящих страниц от 7.10.2026 (tests/Fixtures/sources/flashscore-pages).
 */

function fakePremierLeague(): void
{
    fakeCatalogPages(['football' => ['/football/england/premier-league/']], responses: [
        'www.flashscore.com/football/england/premier-league/' => Http::response(catalogPageSample('premier-league-en')),
        'www.flashscorekz.com/football/england/premier-league/' => Http::response(catalogPageSample('premier-league-ru')),
    ]);
}

it('builds the competition, season, teams and upcoming fixtures from a competition page', function () {
    fakePremierLeague();

    $run = runCatalogImport();

    expect($run->status)->toBe('succeeded');
    $competition = Competition::query()->with('primaryCategory.country')->sole();
    expect($competition->name)->toBe('Premier League')
        ->and($competition->slug)->toBe('premier-league')
        ->and($competition->participant_kind)->toBe('club')
        ->and($competition->primaryCategory->key)->toBe('england')
        ->and($competition->primaryCategory->kind)->toBe('country')
        ->and($competition->primaryCategory->country->kind)->toBe('sporting_territory')
        ->and($competition->primaryCategory->country->iso2)->toBeNull();

    // Сезон — с подписью со страницы и id сезона источника; турнир — по id шаблона.
    $edition = Edition::query()->sole();
    expect($edition->label)->toBe('2026/2027')->and($edition->key)->toBe('2026-2027')->and($edition->is_current)->toBeTrue();
    expect(DB::table('catalog_competition_sources')->value('external_id'))->toBe('dYlOSQOD')
        ->and(DB::table('catalog_edition_sources')->value('external_id'))->toBe('SY30SsKF');

    // Начало — из Unix-времени: 1791631800 = 2026-10-10 11:30 UTC. Только расписание: счёта нет,
    // а прошедшие матчи страницы (Fulham — Manchester Utd) не заводятся.
    $upcoming = Event::query()->whereIn('id', DB::table('catalog_event_sources')->where('external_id', 'xtmHKGT0')->pluck('event_id'))->sole();
    expect($upcoming->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-10 11:30')
        ->and($upcoming->status)->toBe('scheduled')
        ->and($upcoming->round_label)->toBe('Round 6')
        ->and(DB::table('catalog_event_sources')->where('external_id', 'vq6T9aFr')->exists())->toBeFalse();

    expect(Team::query()->where('name', 'Arsenal')->value('kind'))->toBe('club')
        ->and(Sport::query()->where('key', 'football')->value('active'))->toBeTrue()
        ->and(Coverage::query()->sole()->last_success_at)->not->toBeNull();
});

it('takes Russian names from the Russian page by id, not by guessing', function () {
    fakePremierLeague();

    runCatalogImport();

    $arsenal = Team::query()->where('name', 'Arsenal')->sole();
    $russian = Name::query()->where('nameable_type', 'team')->where('nameable_id', $arsenal->id)->where('locale', 'ru')->sole();
    expect($russian->name)->toBe('Арсенал')
        ->and($russian->source_url)->toBe('https://www.flashscorekz.com/football/england/premier-league/')
        ->and(Name::query()->where('nameable_type', 'competition')->where('locale', 'ru')->value('name'))->toBe('Премьер-лига')
        ->and(Name::query()->where('nameable_type', 'category')->where('locale', 'ru')->value('name'))->toBe('Англия')
        ->and(Event::query()->whereIn('id', DB::table('catalog_event_sources')->where('external_id', 'xtmHKGT0')->pluck('event_id'))->value('round_label_local'))->toBe('Тур 6')
        // Русская страница не создаёт сущностей — только названия.
        ->and(Team::query()->count())->toBe(Team::query()->whereHas('names', fn ($q) => $q->where('locale', 'en'))->count());
});

it('is idempotent and never erases known facts with a lighter page', function () {
    fakePremierLeague();
    runCatalogImport();
    $counts = [Team::query()->count(), Event::query()->count(), Competition::query()->count(), Name::query()->count()];
    $logo = Team::query()->where('name', 'Arsenal')->value('logo_asset_id');
    expect($logo)->not->toBeNull();

    // Тот же матч сменил статус, а у Arsenal пропала эмблема — эмблема остаётся.
    $lighter = str_replace(['¬OA÷0n1ffK6k-vcNAdtF9.png', 'AA÷xtmHKGT0¬AD÷1791631800¬ADE÷1791631800¬AB÷1'], ['', 'AA÷xtmHKGT0¬AD÷1791631800¬ADE÷1791631800¬AB÷2'], catalogPageSample('premier-league-en'));
    fakeCatalogPages(['football' => ['/football/england/premier-league/']], responses: [
        'www.flashscore.com/football/england/premier-league/' => Http::response($lighter),
        'www.flashscorekz.com/football/england/premier-league/' => Http::response(catalogPageSample('premier-league-ru')),
    ]);
    runCatalogImport();

    expect([Team::query()->count(), Event::query()->count(), Competition::query()->count(), Name::query()->count()])->toBe($counts)
        ->and(Team::query()->where('name', 'Arsenal')->value('logo_asset_id'))->toBe($logo)
        ->and(Event::query()->whereIn('id', DB::table('catalog_event_sources')->where('external_id', 'xtmHKGT0')->pluck('event_id'))->value('status'))->toBe('live');
});

it('keeps seasons apart: the archive page adds a past edition of the same competition', function () {
    fakeCatalogPages(['football' => ['/football/england/premier-league/', '/football/england/premier-league-2025-2026/results/']], responses: [
        'www.flashscore.com/football/england/premier-league/' => Http::response(catalogPageSample('premier-league-en')),
        'www.flashscore.com/football/england/premier-league-2025-2026/results/' => Http::response(catalogPageSample('premier-league-2025-2026-en')),
    ]);

    runCatalogImport(translations: false);

    expect(Competition::query()->count())->toBe(1)
        ->and(Edition::query()->orderBy('key')->get(['key', 'label', 'is_current'])->toArray())->toBe([
            ['key' => '2025-2026', 'label' => '2025/2026', 'is_current' => false],
            ['key' => '2026-2027', 'label' => '2026/2027', 'is_current' => true],
        ]);
});

it('keeps national teams separate from their region and places one competition in several sections', function () {
    fakeCatalogPages([], teams: ['football' => [['path' => '/team/france/QkGeVG1n/', 'national' => true]]], responses: [
        'www.flashscore.com/team/france/QkGeVG1n/' => Http::response(catalogPageSample('team-france-en')),
    ]);
    config(['sports.catalog.flashscore.national_competitions' => ['/football/europe/uefa-nations-league/', '/football/world/world-championship/', '/football/europe/world-championship/', '/football/world/friendly-international/', '/football/europe/euro/']]);

    runCatalogImport(translations: false);

    $france = Team::query()->with('country')->where('name', 'France')->sole();
    expect($france->kind)->toBe('national')
        ->and($france->gender)->toBe('men')
        ->and($france->age_group)->toBe('senior')
        // Раздел «Европа» — регион навигации; страна сборной — Франция, а не Европа.
        ->and(Category::query()->where('key', 'europe')->value('kind'))->toBe('region')
        ->and(Category::query()->where('key', 'europe')->value('country_id'))->toBeNull();

    // Чемпионат мира — один турнир (один id шаблона) в двух разделах, без копий.
    $worldCup = Competition::query()->where('name', 'World Championship')->sole();
    expect($worldCup->categories()->pluck('key')->sort()->values()->all())->toBe(['europe', 'world'])
        ->and(Competition::query()->where('name', 'UEFA Nations League')->value('participant_kind'))->toBe('national');

    // «UEFA Nations League - League A» — турнир без подписи стадии. Сезон страница
    // команды не называет — события без сезона и стадии, год не угадывается.
    expect(Competition::query()->where('name', 'like', '%League A%')->exists())->toBeFalse()
        ->and(Edition::query()->count())->toBe(0)
        ->and(Event::query()->whereNotNull('edition_id')->exists())->toBeFalse();
});

it('stores tennis doubles as pairs of athletes, never as teams', function () {
    fakeCatalogPages(['tennis' => ['/tennis/atp-doubles/shanghai/']], responses: [
        'www.flashscore.com/tennis/atp-doubles/shanghai/' => Http::response(catalogPageSample('atp-doubles-shanghai-en')),
    ]);

    runCatalogImport(translations: false);

    expect(Team::query()->count())->toBe(0)
        ->and(Entry::query()->where('kind', 'pair')->count())->toBe(6)
        ->and(Entry::query()->where('kind', 'pair')->withCount('members')->get()->every(fn (Entry $entry) => $entry->members_count === 2))->toBeTrue();

    $match = Event::query()->whereIn('id', DB::table('catalog_event_sources')->where('external_id', 'xxF8vLoR')->pluck('event_id'))->sole();
    expect($match->competition->entry_format)->toBe('doubles')
        ->and($match->competition->gender)->toBe('men');

    // Зверев играет в паре с Мело: пара — заявка, у спортсмена — свой профиль.
    $zverev = Athlete::query()->with('nationalities.country')->where('name', 'Zverev A.')->sole();
    expect($zverev->nationalities->sole()->country->name)->toBe('Germany')
        ->and(Entry::query()->where('kind', 'pair')->whereHas('members', fn ($q) => $q->where('athlete_id', $zverev->id))->count())->toBe(1);
});
it('never stores past matches, but lets a known match finish and leave the schedule', function () {
    fakeCatalogPages(['hockey' => ['/hockey/usa/nhl/fixtures/']], responses: [
        'www.flashscore.com/hockey/usa/nhl/fixtures/' => Http::response(catalogPageSample('nhl-en')),
    ]);
    runCatalogImport(translations: false);

    // На странице 9 матчей, из них 3 предстоящих: прошедшие не заводятся.
    expect(Event::query()->count())->toBe(3)
        ->and(Event::query()->where('status', 'finished')->exists())->toBeFalse();

    // Тот же матч завершился — статус обновляется, в расписании его больше нет.
    $finished = str_replace('AA÷lI3fKmg3¬AD÷1791415800¬ADE÷1791415800¬AB÷1¬CR÷1¬AC÷1', 'AA÷lI3fKmg3¬AD÷1791415800¬ADE÷1791415800¬AB÷3¬CR÷3¬AC÷3', catalogPageSample('nhl-en'));
    fakeCatalogPages(['hockey' => ['/hockey/usa/nhl/fixtures/']], responses: [
        'www.flashscore.com/hockey/usa/nhl/fixtures/' => Http::response($finished),
    ]);
    runCatalogImport(translations: false);

    expect(Event::query()->whereIn('id', DB::table('catalog_event_sources')->where('external_id', 'lI3fKmg3')->pluck('event_id'))->value('status'))->toBe('finished')
        ->and(EventFeed::query()->pluck('id')->count())->toBeLessThanOrEqual(2);
});

it('treats a challenge page or a refusal as a failure, keeping what is known', function () {
    fakePremierLeague();
    runCatalogImport(translations: false);
    $events = Event::query()->count();

    fakeCatalogPages(['football' => ['/football/england/premier-league/']], responses: [
        'www.flashscore.com/football/england/premier-league/' => Http::response(file_get_contents(base_path('tests/Fixtures/sources/flashscore/challenge.html'))),
    ]);
    $run = runCatalogImport(translations: false);

    expect($run->status)->toBe('failed')
        ->and(Observation::query()->where('status', 'invalid')->count())->toBe(1)
        ->and(Event::query()->count())->toBe($events)
        ->and(Coverage::query()->sole()->last_failure_at)->not->toBeNull();

    fakeCatalogPages(['football' => ['/football/england/premier-league/', '/football/england/championship/']], responses: [
        'www.flashscore.com/*' => Http::response('Too many requests', 429),
    ]);
    $run = runCatalogImport(translations: false);
    expect($run->status)->toBe('failed')->and($run->pages_failed)->toBe(1);
    Http::assertSentCount(1);
});

it('refuses paths outside public competition and team pages', function () {
    expect(PageFetcher::allowed('/football/england/premier-league/'))->toBeTrue()
        ->and(PageFetcher::allowed('/team/france/QkGeVG1n/'))->toBeTrue()
        ->and(PageFetcher::allowed('/football/england/premier-league/standings/'))->toBeFalse()
        ->and(PageFetcher::allowed('/match/football/abc/'))->toBeFalse()
        ->and(PageFetcher::allowed('/x/feed/f_1_0_3_en_1'))->toBeFalse();
});

it('rolls a dry run back but records what it saw', function () {
    fakePremierLeague();

    $run = runCatalogImport(dryRun: true, translations: false);

    expect($run->dry_run)->toBeTrue()
        ->and($run->events_seen)->toBeGreaterThan(0)
        ->and(Event::query()->count())->toBe(0)
        ->and(Observation::query()->count())->toBe(1);
});

it('makes no requests while the owner has not enabled the import', function () {
    fakePremierLeague();
    config(['sports.catalog.flashscore.enabled' => false]);

    $this->artisan('catalog:import')->assertSuccessful();

    Http::assertNothingSent();
    expect(ImportRun::query()->count())->toBe(0);
});

it('takes the section from the competition address when the block header names another one', function () {
    // France W, 7.10.2026: «EUROPE: World Cup Women - Qualification», а адрес — /football/world/world-cup-women/.
    $page = <<<'HTML'
        <html lang="en"><body><div class="heading__name">France</div>
        <script>cjs.initialFeeds['results'] = { data: `SA÷1¬~ZA÷EUROPE: Euro Women¬ZEE÷2ZqrOpGa¬ZB÷6¬ZY÷Europe¬ZC÷aaaaaaaa¬ZE÷0¬ZL÷/football/europe/euro-women/¬~AA÷AAAAAAA1¬AD÷1890000000¬AB÷1¬AE÷France W¬PX÷G0E4tMc9¬AF÷Spain W¬PY÷BBBBBBB1¬AG÷1¬AH÷0¬AS÷1¬~ZA÷EUROPE: World Cup Women - Qualification - League A¬ZEE÷UyQsXsNl¬ZB÷6¬ZY÷Europe¬ZC÷bbbbbbbb¬ZE÷0¬ZL÷/football/world/world-cup-women/¬ZK÷World Cup Women¬~AA÷AAAAAAA2¬AD÷1890100000¬AB÷1¬AE÷France W¬PX÷G0E4tMc9¬AF÷Italy W¬PY÷BBBBBBB2¬AG÷2¬AH÷2¬AS÷0¬~` };</script></body></html>
        HTML;
    fakeCatalogPages([], teams: ['football' => [['path' => '/team/france/G0E4tMc9/', 'national' => true]]], responses: [
        'www.flashscore.com/team/france/G0E4tMc9/' => Http::response($page),
    ]);
    config(['sports.catalog.flashscore.national_competitions' => ['/football/europe/euro-women/', '/football/world/world-cup-women/']]);

    $run = runCatalogImport(translations: false);

    expect($run->status)->toBe('succeeded')
        ->and(Category::query()->orderBy('key')->get(['key', 'name'])->map(fn ($category) => [$category->key, $category->name])->all())->toBe([['europe', 'Europe'], ['world', 'World']])
        ->and(Competition::query()->where('name', 'World Cup Women')->sole()->primaryCategory->key)->toBe('world')
        ->and(Team::query()->where('name', 'France W')->sole()->gender)->toBe('women')
        ->and(Team::query()->where('name', 'Italy W')->sole()->kind)->toBe('national');
});
