<?php

namespace App\Guide\Competitions;

use App\Guide\TeamCategory;

/**
 * Турниры, которые показываем под своим именем и с эмблемой. Шаблоны
 * проверяются на названии в нижнем регистре (ё → е) — и русском из
 * телегида, и английском из источников, с «хвостами» этапов LiveScore
 * («uefa nations league: league a: group 3»). Начало строки закреплено:
 * «CONCACAF Nations League» — не Лига наций УЕФА.
 *
 * Всё, чего здесь нет, остаётся текстом из телегида (вес — CompetitionRank).
 *
 * logo — эмблема, если источники её не приносят сами (ESPN приносит свои
 * лиги): бейджи TheSportsDB, проверенные по названию лиги (lookupleague).
 */
final class CompetitionCatalog
{
    /**
     * @var list<array{key: string, logo?: string, category?: string, sport: string, name: string, short: string, country: ?string, weight: int, patterns: list<string>}>
     */
    private const array COMPETITIONS = [
        // Футбол
        ['key' => 'ucl', 'sport' => 'football', 'name' => 'Лига чемпионов', 'short' => 'ЛЧ', 'country' => 'EU', 'weight' => 10,
            'patterns' => ['^лига чемпионов', '^(uefa )?champions league']],
        ['key' => 'uel', 'sport' => 'football', 'name' => 'Лига Европы', 'short' => 'ЛЕ', 'country' => 'EU', 'weight' => 7,
            'patterns' => ['^лига европы', '^(uefa )?europa league']],
        ['key' => 'uecl', 'sport' => 'football', 'name' => 'Лига конференций', 'short' => 'ЛК', 'country' => 'EU', 'weight' => 6,
            'patterns' => ['^лига конференций', '^(uefa )?(europa )?conference league']],
        ['key' => 'nations', 'sport' => 'football', 'name' => 'Лига наций', 'short' => 'Лига наций', 'country' => 'EU', 'weight' => 8,
            'patterns' => ['^лига наций', '^uefa nations league', '^uefa nemzetek']],
        ['key' => 'rpl', 'sport' => 'football', 'name' => 'Российская Премьер-лига', 'short' => 'РПЛ', 'country' => 'RU', 'weight' => 8,
            'patterns' => ['^(мир )?рпл', '^российская премьер', '^russian premier league', '^russia: premier league']],
        ['key' => 'fnl', 'sport' => 'football', 'name' => 'Лига Pari', 'short' => 'Лига Pari', 'country' => 'RU', 'weight' => 5,
            'patterns' => ['^лига pari', '^первая лига', '^russia: (first league|fnl)']],
        ['key' => 'epl', 'sport' => 'football', 'name' => 'Премьер-лига', 'short' => 'АПЛ', 'country' => 'GB', 'weight' => 9,
            'patterns' => ['^апл$', '^английская премьер', '^(english )?premier league$', '^england: premier league$']],
        ['key' => 'efl-championship', 'sport' => 'football', 'name' => 'Чемпионшип', 'short' => 'Чемпионшип', 'country' => 'GB', 'weight' => 5,
            'patterns' => ['^чемпионшип', '^(efl|english league) championship', '^england: championship$']],
        ['key' => 'laliga', 'sport' => 'football', 'name' => 'Ла Лига', 'short' => 'Ла Лига', 'country' => 'ES', 'weight' => 8,
            'patterns' => ['^ла лига', '^чемпионат испании', '^(spanish )?la ?liga$', '^spain: la ?liga$']],
        ['key' => 'bundesliga', 'sport' => 'football', 'name' => 'Бундеслига', 'short' => 'Бундеслига', 'country' => 'DE', 'weight' => 8,
            'patterns' => ['^бундеслига$', '^чемпионат германии', '^(german )?bundesliga$', '^germany: bundesliga$']],
        ['key' => 'seriea', 'sport' => 'football', 'name' => 'Серия А', 'short' => 'Серия А', 'country' => 'IT', 'weight' => 7,
            'patterns' => ['^(итальянская )?серия а$', '^чемпионат италии', '^(italian )?serie a$', '^italy: serie a$']],
        ['key' => 'ligue1', 'sport' => 'football', 'name' => 'Лига 1', 'short' => 'Лига 1', 'country' => 'FR', 'weight' => 7,
            'patterns' => ['^лига 1$', '^чемпионат франции', '^(french )?ligue 1$', '^france: ligue 1$']],
        ['key' => 'eredivisie', 'sport' => 'football', 'name' => 'Эредивизи', 'short' => 'Эредивизи', 'country' => 'NL', 'weight' => 5,
            'patterns' => ['^эредивизи', '^чемпионат нидерландов', '^чемпіонат нідерландів', '^(dutch )?eredivisie$', '^netherlands: eredivisie$']],
        ['key' => 'primeira', 'sport' => 'football', 'name' => 'Примейра Лига', 'short' => 'Примейра', 'country' => 'PT', 'weight' => 5,
            'patterns' => ['^примейра', '^чемпионат португалии', '^(portuguese )?(primeira liga|liga portugal)$', '^portugal: liga portugal$']],
        ['key' => 'spfl', 'sport' => 'football', 'name' => 'Шотландская Премьер-лига', 'short' => 'Шотландия', 'country' => 'GB', 'weight' => 5,
            'patterns' => ['^шотландская премьер', '^scottish premiership', '^scotland: premiership$']],
        // Хоккей
        ['key' => 'khl', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/tfmf8l1615492008.png', 'sport' => 'hockey', 'name' => 'КХЛ', 'short' => 'КХЛ', 'country' => 'RU', 'weight' => 8,
            'patterns' => ['^(чемпионат )?кхл', '^kontinental hockey league', '^russian khl', '^russia: khl']],
        ['key' => 'mhl', 'sport' => 'hockey', 'name' => 'МХЛ', 'short' => 'МХЛ', 'country' => 'RU', 'weight' => 4,
            'patterns' => ['^(чемпионат )?мхл']],
        ['key' => 'nhl', 'sport' => 'hockey', 'name' => 'НХЛ', 'short' => 'НХЛ', 'country' => 'US', 'weight' => 8,
            'patterns' => ['^нхл', '^national hockey league', '^nhl']],
        ['key' => 'chl', 'sport' => 'hockey', 'name' => 'Лига чемпионов', 'short' => 'ЛЧ', 'country' => 'EU', 'weight' => 5,
            'patterns' => ['^лига чемпионов', '^champions hockey league', '^champions league']],
        // Баскетбол
        ['key' => 'euroleague', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/7xjtuy1554397263.png', 'sport' => 'basketball', 'name' => 'Евролига', 'short' => 'Евролига', 'country' => 'EU', 'weight' => 7,
            'patterns' => ['^евролига', '^(turkish airlines )?euroleague']],
        ['key' => 'eurocup', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/w54nor1554757326.png', 'sport' => 'basketball', 'name' => 'Еврокубок', 'short' => 'Еврокубок', 'country' => 'EU', 'weight' => 5,
            'patterns' => ['^еврокубок', '^eurocup']],
        ['key' => 'vtb', 'sport' => 'basketball', 'name' => 'Единая лига ВТБ', 'short' => 'ВТБ', 'country' => 'RU', 'weight' => 6,
            'patterns' => ['^(единая )?лига втб', '^втб', '^vtb']],
        ['key' => 'nba', 'sport' => 'basketball', 'name' => 'НБА', 'short' => 'НБА', 'country' => 'US', 'weight' => 8,
            'patterns' => ['^нба', '^nba', '^national basketball association']],
        ['key' => 'bcl', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/6dfp5j1576751638.png', 'sport' => 'basketball', 'name' => 'Лига чемпионов ФИБА', 'short' => 'ЛЧ ФИБА', 'country' => 'EU', 'weight' => 5,
            'patterns' => ['^лига чемпионов', '^basketball champions league', '^champions league']],
        // Волейбол, гандбол, футзал
        ['key' => 'volley-superliga-men', 'sport' => 'volleyball', 'name' => 'Суперлига · Мужчины', 'short' => 'Суперлига', 'country' => 'RU', 'weight' => 6,
            'patterns' => ['^(чемпионат россии · )?суперлига · мужчины']],
        ['key' => 'volley-superliga-women', 'category' => 'w', 'sport' => 'volleyball', 'name' => 'Суперлига · Женщины', 'short' => 'Суперлига', 'country' => 'RU', 'weight' => 6,
            'patterns' => ['^(чемпионат россии · )?суперлига · женщины']],
        ['key' => 'ehf-cl', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/px6tl61704207123.png', 'sport' => 'handball', 'name' => 'Лига чемпионов EHF', 'short' => 'ЛЧ EHF', 'country' => 'EU', 'weight' => 6,
            'patterns' => ['^лига чемпионов', '^ehf champions league', '^europe: champions league']],
        ['key' => 'ehf-el', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/oil7my1704209758.png', 'sport' => 'handball', 'name' => 'Лига Европы EHF', 'short' => 'ЛЕ EHF', 'country' => 'EU', 'weight' => 5,
            'patterns' => ['^лига европы', '^ehf european league', '^europe: european league']],
        ['key' => 'handball-superliga', 'sport' => 'handball', 'name' => 'Суперлига', 'short' => 'Суперлига', 'country' => 'RU', 'weight' => 5,
            'patterns' => ['^(чемпионат россии · )?суперлига']],
        ['key' => 'futsal-superliga', 'sport' => 'futsal', 'name' => 'Суперлига', 'short' => 'Суперлига', 'country' => 'RU', 'weight' => 5,
            'patterns' => ['^(бетсити · )?суперлига']],
        // Остальное
        ['key' => 'f1', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/g8cofl1513623681.png', 'sport' => 'motorsport', 'name' => 'Формула-1', 'short' => 'F1', 'country' => null, 'weight' => 9,
            'patterns' => ['^формула[- ]?1', '^forma-1', '^formula (1|one)', '^formule 1', '^fórmula 1', '^f1(?! ?h2o)']],
        ['key' => 'ufc', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/bewnz31717531281.png', 'sport' => 'combat', 'name' => 'UFC', 'short' => 'UFC', 'country' => 'US', 'weight' => 8,
            'patterns' => ['^ufc']],
        ['key' => 'atp', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/q7aej51769857150.png', 'sport' => 'tennis', 'name' => 'ATP', 'short' => 'ATP', 'country' => null, 'weight' => 6,
            'patterns' => ['^(турнир )?(atp|атр)']],
        ['key' => 'wta', 'logo' => 'https://r2.thesportsdb.com/images/media/league/badge/bddhun1768230678.png', 'sport' => 'tennis', 'name' => 'WTA', 'short' => 'WTA', 'country' => null, 'weight' => 6,
            'patterns' => ['^wta']],
        // Биатлон: женские гонки — отдельный турнир (как женская Лига наций), смешанные — с мужскими.
        ['key' => 'biathlon-wc', 'sport' => 'biathlon', 'name' => 'Кубок мира', 'short' => 'Кубок мира', 'country' => null, 'weight' => 7,
            'patterns' => ['^кубок мира', '^(ibu )?world cup']],
        ['key' => 'biathlon-wc-women', 'category' => 'w', 'sport' => 'biathlon', 'name' => 'Кубок мира · Женщины', 'short' => 'Кубок мира', 'country' => null, 'weight' => 7,
            'patterns' => ['^кубок мира', '^(ibu )?world cup']],
        ['key' => 'biathlon-wch', 'sport' => 'biathlon', 'name' => 'Чемпионат мира', 'short' => 'ЧМ', 'country' => null, 'weight' => 8,
            'patterns' => ['^чемпионат мира', '^(ibu )?world championships?']],
        ['key' => 'biathlon-wch-women', 'category' => 'w', 'sport' => 'biathlon', 'name' => 'Чемпионат мира · Женщины', 'short' => 'ЧМ', 'country' => null, 'weight' => 8,
            'patterns' => ['^чемпионат мира', '^(ibu )?world championships?']],
    ];

    /**
     * @return list<array{key: string, logo?: string, category?: string, sport: string, name: string, short: string, country: ?string, weight: int, patterns: list<string>}>
     */
    public static function all(): array
    {
        return self::COMPETITIONS;
    }

    /**
     * Ключ турнира по названию из телегида или источника; null — не из списка.
     */
    public static function match(string $sport, ?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $category = TeamCategory::of($name);
        $name = str_replace('ё', 'е', mb_strtolower(trim($name)));

        foreach (self::COMPETITIONS as $competition) {
            // Женская Лига наций и Евро U21 — не взрослые мужские турниры.
            if ($competition['sport'] !== $sport || ($competition['category'] ?? '') !== $category) {
                continue;
            }

            foreach ($competition['patterns'] as $pattern) {
                if (preg_match("/{$pattern}/u", $name)) {
                    return $competition['key'];
                }
            }
        }

        return null;
    }
}
