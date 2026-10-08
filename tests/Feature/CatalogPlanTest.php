<?php

use App\Models\User;
use App\Sports\Catalog\CatalogWriter;
use App\Sports\Flashscore\InvalidPage;
use App\Sports\Flashscore\LiteDayParser;
use App\Sports\Models\Competition;
use App\Sports\Models\Event;
use App\Sports\Models\EventEntry;
use App\Sports\Models\Page;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Плановый импорт: дневные списки лёгкой версии → предварительные события,
 * страницы турниров в пределах бюджета → те же события с участниками.
 */

beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 10:00', 'UTC')));
afterEach(fn () => CarbonImmutable::setTestNow());

/** Дневной список: заголовок турнира и строки «время Хозяева - Гости <a …>счёт</a>». */
function liteDay(string $sport, array $rows, int $offset = 0): string
{
    $label = [0 => 'Today', 1 => 'Tomorrow'][$offset];
    $html = "<html><body><h2>{$sport} » {$label} » All Games</h2><div id=\"score-data\">";
    if ($rows === []) {
        $html .= '<div><span>No match found.</span></div>';
    }
    foreach ($rows as $header => $matches) {
        $html .= "<h4>{$header}</h4>";
        foreach ($matches as [$time, $teams, $id, $state, $score]) {
            $html .= "<span>{$time}</span>{$teams} <a href=\"/match/{$id}/\" class=\"{$state}\">{$score}</a><br />";
        }
    }

    return $html.'</div></body></html>';
}

/** Все дневные списки пусты, кроме переданных: [раздел => [смещение => html]]. */
function fakeLite(array $pages): Closure
{
    return function (Request $request) use ($pages) {
        preg_match('~flashscore\.mobi/([a-z-]*)/?~', $request->url(), $match);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $offset = (int) ($query['d'] ?? 0);

        return Http::response($pages[$match[1] ?? ''][$offset] ?? liteDay('Sport', [], $offset));
    };
}

function planned(): void
{
    config([
        'sports.catalog.flashscore.pause_seconds' => 0,
        'sports.catalog.flashscore.cache_hours' => 0,
        'sports.catalog.flashscore.lite.cache_minutes' => 0,
        'sports.catalog.flashscore.lite.days' => [0],
        'sports.catalog.flashscore.lite.enabled' => true,
        'sports.catalog.flashscore.pages_per_run' => 5,
    ]);
}

it('reads a day list: headers, times, states and walkovers', function () {
    $html = liteDay('Hockey', ['USA: NHL' => [['01:30', 'Los Angeles Kings - Florida Panthers', '6s6UtjWi', 'fin', '1-2'], ['18:00', 'Guinea W - Cameroon W WO', 'AbCdEf12', 'fin', '&nbsp;-&nbsp;']]]);

    $rows = (new LiteDayParser)->parse($html, 0, CarbonImmutable::now());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['header' => 'USA: NHL', 'id' => '6s6UtjWi', 'time' => '01:30', 'state' => 'fin', 'home' => 'Los Angeles Kings', 'away' => 'Florida Panthers', 'score' => [1, 2]])
        ->and($rows[1]['walkover'])->toBeTrue()
        ->and(fn () => (new LiteDayParser)->parse($html, 1, CarbonImmutable::now()))->toThrow(InvalidPage::class);
});

it('shows today from the day list at once and upgrades the same events when the competition page arrives', function () {
    planned();
    fakeCatalogPages(['hockey' => ['/hockey/usa/nhl/']], responses: [
        'www.flashscore.mobi/*' => fakeLite(['hockey' => [0 => liteDay('Hockey', [
            'USA: NHL' => [['23:30', 'Washington Capitals - Pittsburgh Penguins', 'lI3fKmg3', 'sched', '&nbsp;-&nbsp;']],
            'NOWHERE: Unknown Cup' => [['19:00', 'A - B', 'ZzZzZzZ1', 'sched', '&nbsp;-&nbsp;']],
        ])]]),
        'www.flashscore.com/hockey/usa/nhl/' => Http::response(catalogPageSample('nhl-en')),
        'www.flashscore.com/hockey/nowhere/unknown-cup/' => Http::response('Not found', 404),
    ]);
    config(['sports.catalog.flashscore.hosts' => ['en' => 'https://www.flashscore.com']]);

    // Только дневной список: страница турнира не загружена — событие предварительное, турнир — по адресу.
    config(['sports.catalog.flashscore.pages_per_run' => 0]);
    (new CatalogWriter)->syncSports();
    DB::table('catalog_providers')->insertOrIgnore(['key' => 'flashscore', 'name' => 'Flashscore', 'created_at' => now(), 'updated_at' => now()]);
    $sport = DB::table('catalog_sports')->where('key', 'hockey')->value('id');
    Page::query()->create(['provider_id' => DB::table('catalog_providers')->value('id'), 'sport_id' => $sport, 'path' => '/hockey/usa/nhl/', 'origin' => 'sitemap']);
    runCatalogImportPlanned();

    $event = Event::query()->sole();
    $sides = EventEntry::query()->where('event_id', $event->id)->orderBy('side')->get();
    expect($sides->pluck('label')->all())->toBe(['Washington Capitals', 'Pittsburgh Penguins'])
        ->and($sides->pluck('entry_id')->filter()->all())->toBe([])
        ->and($event->status)->toBe('scheduled')
        ->and(Competition::query()->sole()->identity_state)->toBe('provisional')
        // Заголовок без адреса в реестре — адрес-кандидат на проверку, а не выдуманный турнир.
        ->and(Page::query()->where('origin', 'probe')->value('path'))->toBe('/hockey/nowhere/unknown-cup/');

    // Плановый запуск с бюджетом: страница турнира — тот же турнир и то же событие, уже с командами.
    config(['sports.catalog.flashscore.pages_per_run' => 5]);
    runCatalogImportPlanned();

    expect(Competition::query()->count())->toBe(1)
        ->and(Competition::query()->sole()->identity_state)->toBe('verified')
        ->and(Event::query()->whereIn('id', DB::table('catalog_event_sources')->where('external_id', 'lI3fKmg3')->pluck('event_id'))->count())->toBe(1)
        ->and(EventEntry::query()->where('event_id', $event->id)->whereNull('entry_id')->count())->toBe(0)
        ->and(EventEntry::query()->where('event_id', $event->id)->where('entry_status', 'unlisted')->count())->toBe(0)
        ->and(Page::query()->where('path', '/hockey/nowhere/unknown-cup/')->value('failed_at'))->not->toBeNull()
        ->and(Page::query()->where('path', '/hockey/usa/nhl/')->value('imported_at'))->not->toBeNull();
});

it('pages long competition lists and remembers how much is shown in the address', function () {
    config(['sports.mode' => 'standalone']);
    $writer = new CatalogWriter;
    $sport = $writer->sport('hockey');
    $sport->update(['active' => true]);
    $category = $writer->category($sport, 'country', 'usa', 'USA', country: $writer->country('usa', 'USA'));
    $competition = $writer->competition($sport, $category, ['name' => 'NHL', 'slug' => 'nhl']);
    foreach (range(1, 61) as $day) {
        $writer->event($competition, ['kind' => 'match', 'status' => 'scheduled', 'starts_at' => CarbonImmutable::parse('2026-11-01')->addDays($day), 'date' => CarbonImmutable::parse('2026-11-01')->addDays($day)->toDateString()]);
    }
    $this->actingAs(User::factory()->create());

    $this->get('/sport/hockey/usa/nhl')
        ->assertInertia(fn (Assert $page) => $page->has('fixtures', 60)->where('more', true)->where('limit', 60));
    $this->get('/sport/hockey/usa/nhl?limit=120')
        ->assertInertia(fn (Assert $page) => $page->has('fixtures', 61)->where('more', false));
});
