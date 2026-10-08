<?php

use App\Sports\Models\Athlete;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Entry;
use App\Sports\Models\Event;
use App\Sports\Models\EventEntry;
use App\Sports\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * Все виды спорта каталога: каждое семейство событий — в своём виде
 * (команды, спортсмены, пары, этапы с сессиями и участниками), только
 * предстоящее. Образцы — уменьшенные копии настоящих страниц от 7.10.2026.
 */

function importSample(string $sport, string $path, string $sample): void
{
    fakeCatalogPages([$sport => [$path]], responses: ["www.flashscore.com{$path}" => Http::response(catalogPageSample($sample))]);
    expect(runCatalogImport(translations: false)->status)->toBe('succeeded');
}

function eventBySource(string $id, string $scope = 'event'): Event
{
    return Event::query()->whereIn('id', DB::table('catalog_event_sources')->where('external_scope', $scope)->where('external_id', $id)->pluck('event_id'))->sole();
}

/** Участники события по сторонам: названия команд или спортсменов. */
function sidesOf(Event $event): array
{
    return EventEntry::query()->with('entry.team', 'entry.athletes')->where('event_id', $event->id)->orderBy('side')->get()
        ->map(fn (EventEntry $row) => $row->entry->team->name ?? $row->entry->athletes->pluck('name')->implode(' / '))
        ->all();
}

it('stores team sports as matches between teams', function (string $sport, string $path, string $sample, string $id, array $teams) {
    importSample($sport, $path, $sample);

    $match = eventBySource($id);
    expect($match->kind)->toBe('match')
        ->and($match->status)->toBe('scheduled')
        ->and(sidesOf($match))->toBe($teams)
        ->and(Team::query()->where('kind', 'club')->count())->toBeGreaterThan(0);
})->with([
    'baseball' => ['baseball', '/baseball/usa/mlb/', 'mlb-en', 'Aifzzhn2', ['Chicago White Sox', 'Cleveland Guardians']],
    'volleyball' => ['volleyball', '/volleyball/italy/superlega/', 'superlega-en', 'MwCumVaT', ['Verona', 'Lube Civitanova']],
    'cricket' => ['cricket', '/cricket/australia/big-bash-league/results/', 'bbl-en', 'SKjKOBIa', ['Melbourne Renegades', 'Perth Scorchers']],
]);

it('stores a fight as a bout between two athletes in a weight class', function () {
    importSample('mma', '/mma/lightweight-men/pfl/', 'mma-pfl-en');

    $bout = eventBySource('4IixwhLc');
    expect($bout->kind)->toBe('bout')
        ->and(sidesOf($bout))->toBe(['Shabliy A.', 'Ward L.'])
        ->and(Team::query()->count())->toBe(0)
        ->and(Category::query()->where('key', 'lightweight-men')->value('kind'))->toBe('weight_class')
        ->and(Competition::query()->sole()->gender)->toBe('men');
});

it('stores table-tennis doubles as pairs of athletes', function () {
    importSample('table-tennis', '/table-tennis/others-doubles-men/china-smash/', 'table-tennis-doubles-en');

    $match = eventBySource('Ya2iUp9M');
    expect(sidesOf($match))->toBe(['Lebrun A. / Lebrun F.', 'Wong Chun Ting / Chan B. H. W.'])
        ->and(Entry::query()->where('kind', 'pair')->count())->toBeGreaterThan(0)
        ->and(Competition::query()->sole()->entry_format)->toBe('doubles');
});

it('builds a Grand Prix as a meeting with sessions, drivers and their teams — never home and away', function () {
    importSample('motorsport', '/auto-racing/formula-1/singapore-grand-prix/', 'f1-singapore-en');

    $meeting = Event::query()->where('kind', 'meeting')->sole();
    $sessions = Event::query()->where('parent_id', $meeting->id)->orderBy('starts_at')->get();
    expect($sessions->count())->toBeGreaterThan(3)
        ->and($sessions->first()->name)->toBe('Practice 1')
        ->and($sessions->first()->venue)->toBe('Marina Bay Street Circuit')
        ->and(DB::table('catalog_event_entries')->whereIn('event_id', $sessions->pluck('id'))->whereNotNull('side')->exists())->toBeFalse();

    $gasly = Athlete::query()->where('name', 'Gasly P.')->sole();
    $entry = Entry::query()->whereHas('members', fn ($q) => $q->where('athlete_id', $gasly->id))->sole();
    expect(DB::table('catalog_event_entries')->where('event_id', $sessions->first()->id)->where('entry_id', $entry->id)->value('affiliation'))->toBe('Alpine');

    // Раздел — серия внутри группы «Автогонки», а не страна этапа.
    $series = Category::query()->where('key', 'auto-racing-formula-1')->sole();
    expect($series->kind)->toBe('series')->and($series->parent->key)->toBe('auto-racing');
});

it('builds a one-day race with its riders and their teams', function () {
    importSample('cycling', '/cycling/races/il-lombardia/', 'il-lombardia-en');

    $race = Event::query()->sole();
    expect($race->kind)->toBe('race')
        ->and($race->venue)->toBe('Bergamo-Como · 239.0 km')
        ->and(DB::table('catalog_event_entries')->where('event_id', $race->id)->count())->toBe(4);
});

it('does not store races and tournaments that are already over', function (string $sport, string $path, string $sample) {
    importSample($sport, $path, $sample);

    expect(Event::query()->count())->toBe(0);
})->with([
    'biathlon sprint' => ['winter-sports', '/biathlon/sprint-men/oestersund/', 'biathlon-en'],
    'golf tournament' => ['golf', '/golf/dp-world-tour/bmw-pga-championship/', 'golf-bmw-en'],
]);
