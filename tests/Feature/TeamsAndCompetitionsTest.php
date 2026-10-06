<?php

use App\Guide\Competitions\CompetitionCatalog;
use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Teams\TeamRegistry;
use App\Models\Competition;

it('maps competition names from the guide and the sources', function (string $sport, string $name, ?string $key): void {
    expect(CompetitionCatalog::match($sport, $name))->toBe($key);
})->with([
    ['hockey', 'КХЛ', 'khl'],
    ['hockey', 'Чемпионат КХЛ', 'khl'],
    ['hockey', 'Russian KHL', 'khl'],
    ['football', 'UEFA Nations League: League A: Group 3', 'nations'],
    ['football', 'Лига наций', 'nations'],
    ['football', 'England: Premier League', 'epl'],
    ['football', 'АПЛ', 'epl'],
    ['football', 'Чемпионат Германии', 'bundesliga'],
    ['basketball', 'EuroLeague: EuroLeague: Regular season', 'euroleague'],
    ['volleyball', 'Чемпионат России · Суперлига · Женщины', 'volley-superliga-women'],
    // Ловушки: похоже, но не то.
    ['football', 'CONCACAF Nations League: League A: Group 2', null],
    ['football', 'Canada: Premier League', null],
    ['football', 'Brazil: Serie A', null],
    ['football', 'Algeria: Ligue 1', null],
    ['football', 'Германия', null],
    ['basketball', 'КХЛ', null],
]);

it('syncs the catalog with seeded league logos', function (): void {
    (new CompetitionRegistry)->sync();

    $khl = Competition::query()->where('key', 'khl')->sole();
    expect($khl->name)->toBe('КХЛ')
        ->and($khl->logo_url)->toContain('r2.thesportsdb.com')
        ->and(Competition::query()->count())->toBe(count(CompetitionCatalog::all()));
});

it('keeps the better logo when several sources bring one', function (): void {
    $teams = new TeamRegistry;

    $teams->fromSource('hockey', 'СКА', 'livescore', 'https://livescore.test/ska.png');
    $teams->fromSource('hockey', 'СКА', 'khl', 'https://khl.test/ska.png');
    $team = $teams->fromSource('hockey', 'СКА', 'livescore', 'https://livescore.test/ska-2.png');

    expect($team->fresh()->logo_url)->toBe('https://khl.test/ska.png')
        ->and($team->fresh()->logo_source)->toBe('khl');
});

it('does not learn a plain country name for a women or youth team', function (): void {
    $teams = new TeamRegistry;
    $women = $teams->fromSource('football', 'Ireland Women', 'livescore', null);
    $men = $teams->fromSource('football', 'Ireland', 'espn', null);

    $teams->learn($women, 'Ирландия');
    $teams->learn($men, 'Ирландия');

    expect($women->fresh()->name)->toBe('Ireland Women')
        ->and((new TeamRegistry)->find('football', 'Ирландия')?->is($men))->toBeTrue();
});

it('keeps women and youth competitions apart from the senior ones', function (string $name, ?string $key): void {
    expect(CompetitionCatalog::match('football', $name))->toBe($key);
})->with([
    ['UEFA Nations League Women: League A', null],
    ['Лига наций', 'nations'],
]);
