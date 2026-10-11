<?php

use App\Guide\Fixtures\NameSimilarity;
use App\Guide\Teams\TeamRegistry;
use App\Models\TeamAlias;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-10-06 17:00', 'UTC'));
    config([
        'sports.fixtures.sources.thesportsdb.sports' => ['hockey' => 'Ice Hockey'],
        'sports.fixtures.days_ahead' => 0,
    ]);
});

it('compares Russian and English team names', function (string $ru, string $en, float $min, float $max): void {
    expect(NameSimilarity::score($ru, $en))->toBeGreaterThanOrEqual($min)->toBeLessThanOrEqual($max);
})->with([
    ['Динамо (Москва)', 'Dynamo Moscow', 1.0, 1.0],
    ['Шанхайские Драконы', 'Shanghai Dragons', 1.0, 1.0],
    ['Салават Юлаев (Уфа)', 'Salavat Yulaev Ufa', 1.0, 1.0],
    ['Бавария', 'Bayern Munich', 1.0, 1.0],
    ['Ак Барс', 'Avangard Omsk', 0.0, 0.0],
    ['Ньюкасл', 'Newcastle United', 1.0, 1.0],
    ['Тоттенхэм', 'Tottenham Hotspur', 1.0, 1.0],
    // Сборные — по названиям стран из ICU, точно.
    ['Нидерланды', 'Netherlands', 1.0, 1.0],
    ['Фарерские острова', 'Faroe Islands', 1.0, 1.0],
    ['Ирландия', 'Iceland', 0.0, 0.0],
    ['Нидерланды', 'Ireland Women U19', 0.0, 0.0],
    ['Германия', 'Armenia', 0.0, 0.0],
    ['Манчестер Сити', 'Cerrito', 0.0, 0.0],
    // Одного общего слова мало.
    ['Реал Мадрид', 'Real Cartagena', 0.0, 0.0],
    ['Аль-Хиляль', 'Al Fateh', 0.0, 0.0],
    ['Аль-Хиляль', 'Al Hilal', 1.0, 1.0],
    ['Атлетико Мадрид', 'Atletico FC', 0.5, 0.5],
    ['Динамо (Москва)', 'Dynamo Minsk', 0.0, 0.0],
    // Сокращения: «Мг» — Магнитогорск, но не Новокузнецк.
    ['Металлург (Мг)', 'Metallurg Magnitogorsk', 1.0, 1.0],
    ['Металлург (Мг)', 'Metallurg Novokuznetsk', 0.0, 0.0],
    ['Бавария', 'Ben Aknoun', 0.0, 0.0],
    ['Эльверсберг', 'JS El Biar', 0.0, 0.0],
    // beIN SPORTS XTRA, 12.10.2026: «Paris FC» — другой клуб, город — лишь половина совпадения.
    ['Paris Saint Germain', 'Paris FC', 0.0, 0.5],
    // Okko Футбол, 10.10.2026: одни двухбуквенные коды — не совпадение.
    ['Магдебург', 'America MG', 0.0, 0.0],
]);

it('does not crash when another registry already took a spelling', function (): void {
    $stale = new TeamRegistry;
    $team = $stale->fromSource('handball', 'Magdeburg', 'thesportsdb', null);
    (new TeamRegistry)->fromSource('handball', 'SC Magdeburg', 'flashscore', null);

    // Устаревший кэш первого реестра не знает про «sc magdeburg».
    $stale->learn($team, 'SC Magdeburg');

    expect(TeamAlias::query()->where('alias', 'sc magdeburg')->sole()->team_id)->not->toBe($team->id);
});
