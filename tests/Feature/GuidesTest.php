<?php

use App\Models\Airing;
use App\Models\Channel;
use Carbon\CarbonImmutable;

// Телегид — свой: каналы привязываются к XMLTV сами, соперники ищутся и в описании.

beforeEach(function (): void {
    config(['sports.source' => 'dispatcharr']);
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
});

it('links channels to the guide by tvg-id', function (): void {
    fakeDispatcharr();

    $this->artisan('guide:import')->assertSuccessful();

    expect(Channel::query()->orderBy('number')->get(['tvg_id', 'guide_source', 'guide_channel'])->toArray())->toBe([
        ['tvg_id' => 'MatchPlaneta.ru@SD', 'guide_source' => 'test', 'guide_channel' => 'MatchPlaneta.ru'],
        ['tvg_id' => '2050', 'guide_source' => 'test', 'guide_channel' => '2050'],
        // Его в телегиде нет, а «3» — номер канала, не tvg-id.
        ['tvg_id' => null, 'guide_source' => null, 'guide_channel' => null],
    ]);
});

it('finds the teams in the description of a foreign guide', function (): void {
    $fixture = scheduleMatch('hockey', 'Bili Tygri Liberec', 'Frolunda HC', '2026-10-06 18:00', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr([['2', '20261006175000', '20261006203000', 'Lední hokej', 'CHL, základní část Bílí Tygři Liberec - Frölunda.']]);

    $this->artisan('guide:import')->assertSuccessful();

    expect(matchOf($fixture)->airings()->sole()->live)->toBeTrue();
});

it('keeps the previous guide file when a download fails', function (): void {
    $fixture = scheduleMatch('handball', 'CSKA Moscow', 'SGAU Saratov', '2026-10-06 16:30', ['source' => 'livescore']);
    buildMatches();
    fakeDispatcharr();
    $this->artisan('guide:import')->assertSuccessful();

    fakeDispatcharr(guideDown: true);
    $this->artisan('guide:import')
        ->expectsOutputToContain('Guide not updated: Test')
        ->assertSuccessful();

    expect(matchOf($fixture)->airings()->count())->toBe(3);
});

it('fails without touching data when no guide can be loaded', function (): void {
    fakeDispatcharr(guideDown: true);

    $this->artisan('guide:import')->assertFailed();

    expect(Airing::query()->count())->toBe(0);
});

it('uses a manual link instead of the found one', function (): void {
    fakeDispatcharr();
    config(['sports.guides.channels' => ['Матч! Игра' => null, 'FOX Sports' => 'test:MatchPlaneta.ru']]);

    $this->artisan('guide:import')->assertSuccessful();

    expect(Channel::query()->where('name', 'Матч! Игра')->value('guide_channel'))->toBeNull()
        ->and(Channel::query()->where('name', 'FOX Sports')->value('guide_channel'))->toBe('MatchPlaneta.ru');
});
