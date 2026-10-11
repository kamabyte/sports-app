<?php

use App\Sports\Catalog\CatalogWriter;
use App\Sports\Catalog\InvalidCatalogData;
use App\Sports\Catalog\Names;
use App\Sports\Catalog\SourceIds;
use App\Sports\Models\Athlete;
use App\Sports\Models\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/*
 * Правила каталога, которые проверяет запись: география отдельно от
 * навигации, разделы без циклов, вид спорта совпадает, пара — не команда.
 */

beforeEach(function () {
    $this->writer = new CatalogWriter;
    $this->writer->syncSports();
    $this->football = $this->writer->sport('football');
    $this->tennis = $this->writer->sport('tennis');
});

it('requires a country category to point to a country and a tour category to point to nothing', function () {
    $france = $this->writer->country('france', 'France');

    expect(fn () => $this->writer->category($this->football, 'country', 'france', 'France'))->toThrow(InvalidCatalogData::class)
        ->and(fn () => $this->writer->category($this->tennis, 'tour', 'atp', 'ATP', country: $france))->toThrow(InvalidCatalogData::class)
        ->and(fn () => $this->writer->category($this->football, 'country', 'national-teams', 'X', country: $france))->toThrow(InvalidCatalogData::class);
});

it('rejects cyclic and cross-sport category trees and placements', function () {
    $atp = $this->writer->category($this->tennis, 'tour', 'atp', 'ATP');
    $singles = $this->writer->category($this->tennis, 'tour', 'atp-singles', 'ATP Singles', parent: $atp);

    expect(fn () => $this->writer->category($this->tennis, 'tour', 'atp', 'ATP', parent: $singles))->toThrow(InvalidCatalogData::class)
        ->and(fn () => $this->writer->category($this->football, 'other', 'x', 'X', parent: $atp))->toThrow(InvalidCatalogData::class);

    $world = $this->writer->category($this->football, 'region', 'world', 'World', region: $this->writer->region('world', 'World'));
    $competition = $this->writer->competition($this->football, $world, ['name' => 'World Championship']);
    expect(fn () => $this->writer->place($competition, $atp))->toThrow(InvalidCatalogData::class)
        ->and(fn () => $this->writer->competition($this->tennis, $world, ['name' => 'X']))->toThrow(InvalidCatalogData::class);
});

it('keeps a doubles pair as two athletes and never lets one play against themselves', function () {
    $a = $this->writer->athlete($this->tennis, ['name' => 'A']);
    $b = $this->writer->athlete($this->tennis, ['name' => 'B']);
    $c = $this->writer->athlete($this->tennis, ['name' => 'C']);
    $atp = $this->writer->category($this->tennis, 'tour', 'atp-doubles', 'ATP Doubles');
    $competition = $this->writer->competition($this->tennis, $atp, ['name' => 'Beijing']);
    $event = $this->writer->event($competition, ['kind' => 'match', 'status' => 'scheduled']);

    expect(fn () => $this->writer->pairEntry($this->tennis, $a, $a))->toThrow(InvalidCatalogData::class);

    // Та же пара — та же заявка; новый партнёр — новая заявка; спортсмены те же.
    $ab = $this->writer->pairEntry($this->tennis, $a, $b);
    expect($this->writer->pairEntry($this->tennis, $a, $b)->id)->toBe($ab->id)
        ->and($this->writer->pairEntry($this->tennis, $a, $c)->id)->not->toBe($ab->id)
        ->and(Athlete::query()->count())->toBe(3);

    expect(fn () => $this->writer->eventEntries($event, [['side' => 1, 'entry' => $ab], ['side' => 2, 'entry' => $this->writer->pairEntry($this->tennis, $b, $c)]]))
        ->toThrow(InvalidCatalogData::class);

    $team = $this->writer->team($this->football, ['name' => 'Arsenal']);
    expect(fn () => $this->writer->eventEntries($event, [['side' => 1, 'entry' => $this->writer->teamEntry($team)]]))->toThrow(InvalidCatalogData::class);
});

it('links editions and stages only within their own competition', function () {
    $england = $this->writer->category($this->football, 'country', 'england', 'England', country: $this->writer->country('england', 'England'));
    $league = $this->writer->competition($this->football, $england, ['name' => 'Premier League']);
    $cup = $this->writer->competition($this->football, $england, ['name' => 'FA Cup']);
    $season = $this->writer->edition($league, '2026-2027', ['label' => '2026/2027']);

    expect(fn () => $this->writer->event($cup, ['kind' => 'match', 'edition_id' => $season->id]))->toThrow(InvalidCatalogData::class);
});

it('does not roll a final result back to scheduled from a stale page', function () {
    $england = $this->writer->category($this->football, 'country', 'england', 'England', country: $this->writer->country('england', 'England'));
    $league = $this->writer->competition($this->football, $england, ['name' => 'Premier League']);
    $provider = $this->writer->provider('flashscore', 'Flashscore');
    $source = [$provider, 'event', 'AbCdEf12'];

    $this->writer->event($league, ['kind' => 'match', 'status' => 'finished', 'result_state' => 'final', 'winner_side' => 1], $source);
    $event = $this->writer->event($league, ['kind' => 'match', 'status' => 'scheduled', 'result_state' => 'none'], $source);

    expect($event->status)->toBe('finished')->and($event->winner_side)->toBe(1)->and(Event::query()->count())->toBe(1);
});

it('treats source ids as case-sensitive and never relinks one id to another entity', function () {
    $provider = $this->writer->provider('flashscore', 'Flashscore');
    $upper = $this->writer->team($this->football, ['name' => 'France'], [$provider, 'participant', 'QkGeVG1n']);
    $lower = $this->writer->team($this->football, ['name' => 'Other'], [$provider, 'participant', 'qkgevg1n']);

    expect($upper->id)->not->toBe($lower->id)
        ->and($this->writer->team($this->football, ['name' => 'France (renamed)'], [$provider, 'participant', 'QkGeVG1n'])->id)->toBe($upper->id)
        ->and(fn () => SourceIds::link('team', $provider->id, 'participant', 'QkGeVG1n', $lower->id))->toThrow(InvalidCatalogData::class);
});

it('normalizes names for search without mixing up scripts', function () {
    expect(Names::normalize('Ëlïas  Ångström-Øre'))->toBe('elias angstrom øre')
        ->and(Names::normalize('Рублёв А.'))->toBe('рублев а')
        ->and(Names::normalize('Йокерит'))->toBe('йокерит');
});

it('makes no legacy schedule or logo requests in standalone mode', function () {
    // Телегиды и Dispatcharr в каталоге работают (CatalogBroadcastsTest), прежние источники расписания — нет.
    config(['sports.mode' => 'standalone', 'sports.fixtures.sources.espn.enabled' => true, 'sports.fixtures.sources.livescore.enabled' => true]);
    Http::preventStrayRequests();
    Http::fake();

    foreach (['fixtures:import', 'logos:sync'] as $command) {
        expect(Artisan::call($command))->toBe(0);
    }

    Http::assertNothingSent();
});
