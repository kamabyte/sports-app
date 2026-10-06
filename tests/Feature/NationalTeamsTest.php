<?php

use App\Guide\EventCatalog;
use App\Guide\Teams\NationalTeams;
use App\Guide\Teams\TeamRegistry;
use App\Models\Team;
use Carbon\CarbonImmutable;

it('recognizes national teams in Russian and English', function (string $name, ?string $code, string $category = ''): void {
    $parsed = NationalTeams::parse($name);

    expect($parsed['code'] ?? null)->toBe($code);
    if ($code !== null) {
        expect($parsed['category'])->toBe($category);
    }
})->with([
    ['Франция', 'fr'],
    ['France', 'fr'],
    ['Россия', 'ru'],
    ['Турция', 'tr'],
    ['Türkiye', 'tr'],
    ['Turkey', 'tr'],
    ['Англия', 'gb-eng'],
    ['Северная Ирландия', 'gb-nir'],
    ['Фарерские острова', 'fo'],
    ['Bosnia & Herzegovina', 'ba'],
    ['Босния и Герцеговина', 'ba'],
    ['Czech Republic', 'cz'],
    ['England U21', 'gb-eng', 'u21'],
    ['Ireland Women', 'ie', 'w'],
    ['Kazakhstan W', 'kz', 'w'],
    ['Тулуза', null],
    ['Динамо (Минск)', null],
    ['Европа', null],
]);

it('treats a match as national only when both sides are countries', function (): void {
    expect(NationalTeams::match('Франция', 'Бельгия'))->not->toBeNull()
        // Лига 1, а не сборная Монако.
        ->and(NationalTeams::match('Монако', 'Тулуза'))->toBeNull();
});

it('keeps one national team per country and category', function (): void {
    $teams = new TeamRegistry;

    $guide = $teams->national('football', 'Франция', 'fr', '', 'guide');
    $espn = $teams->national('football', 'France', 'fr', '', 'espn');
    $youth = $teams->national('football', 'France U21', 'fr', 'u21', 'livescore');
    // Женский отбор: «Ирландия» без категории в названии — не написание женской сборной.
    $women = $teams->national('football', 'Ирландия', 'ie', 'w', 'guide');

    expect($espn->is($guide))->toBeTrue()
        ->and($youth->is($guide))->toBeFalse()
        ->and($guide->fresh()->logo_url)->toBe('https://flagcdn.com/w320/fr.png')
        ->and($guide->fresh()->logo_source)->toBe('flag')
        ->and((new TeamRegistry)->find('football', 'France')?->is($guide))->toBeTrue()
        ->and((new TeamRegistry)->find('football', 'Ирландия'))->toBeNull()
        ->and($women->fresh()->logo_url)->toBe('https://flagcdn.com/w320/ie.png');
});

it('shows national team matches from the schedule with flags', function (): void {
    config(['sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
    $nations = scheduleMatch('football', 'France', 'Belgium', '2026-10-06 18:00', ['source' => 'espn', 'competition' => 'UEFA Nations League']);
    // Клуб с названием страны — не сборная.
    $ligue1 = scheduleMatch('football', 'AS Monaco', 'Toulouse', '2026-10-06 18:00', ['source' => 'espn']);
    buildMatches();
    fakeDispatcharr([
        ['1', '20261006180000', '20261006200000', 'Футбол. Франция – Бельгия. Лига наций'],
        ['2', '20261006180000', '20261006200000', 'Монако – Тулуза. Футбол. Чемпионат Франции'],
    ]);

    $this->artisan('guide:import')->assertSuccessful();
    Team::query()->update(['logo_path' => 'logos/teams/flag.webp']);
    $found = app(EventCatalog::class)->find((string) matchOf($nations)->id);

    expect($found->home->flag)->toBeTrue()
        ->and($found->away->flag)->toBeTrue()
        ->and($found->title)->toBe('Франция — Бельгия')
        ->and(Team::query()->find(matchOf($nations)->home_team_id)->logo_url)->toBe('https://flagcdn.com/w320/fr.png')
        ->and(app(EventCatalog::class)->find((string) matchOf($ligue1)->id)->home->flag)->toBeFalse();
});
