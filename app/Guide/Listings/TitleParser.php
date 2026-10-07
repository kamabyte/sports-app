<?php

namespace App\Guide\Listings;

use App\Guide\SportRegistry;
use Carbon\CarbonImmutable;

/**
 * Разбирает название передачи из телегида в спортивное событие. Форматы
 * у каналов разные, разбор — по сегментам между «. », «, » и «: »:
 *
 *   Футбол. АПЛ, 4 тур, Лидс – Ньюкасл                       (Setanta)
 *   ▶️ Гандбол. Чемп. России. Суперлига. Мужчины. ЦСКА - "СГАУ-Саратов".  (Матч!)
 *   Цедевита-Олимпия (Словения) – Хапоэль И (Израиль). Баскетбол. Еврокубок  (Okko)
 *   ⋗ КХЛ. Динамо (Минск) – СКА                               (КХЛ)
 *   Labdarúgás: UEFA Nemzetek Ligája - Ukrajna - Magyarország mérkőzés.  (M4 Sport)
 *   Lední hokej. CHL, základní část Bílí Tygři Liberec - Frölunda           (Sport1 CZ: название + описание)
 *   WNBA Basketball. Atlanta Dream at Washington Mystics               (США: хозяева — вторые)
 *   Euroleague. Anadolu Efes (Tur) vs. Real Madrid (Esp), Fecha 2      (Мексика)
 *   Bordeaux-Bègles / Lyon. Top 14 5e Journée                          (Canal+)
 *   Handbal masculin. LIGA ZIMBRILOR ET4. AHC Potaissa Turda-CSU Suceava  (TVR)
 *
 * Возвращает null для всего, что не событие: обзоров, студий, новостей,
 * передач без турнира («Теннис»).
 */
final class TitleParser
{
    private const array LIVE_MARKERS = ['▶️', '▶', '⋗', '►'];

    private const string LIVE_PHRASES = '/(?<!\p{L})(прямая трансляция|прямой эфир|пряма трансляція|live|élő|živě|naživo|uživo|en vivo|en directo|en direct|in diretta|transmisiune directă|transmisiune directa)(?!\p{L})/iu';

    /** Повтор: «(Reluare)», «ism.», «záznam». */
    private const string REPLAY_PHRASES = '/\(?(?<!\p{L})(reluare|ism\.|ismétlés|záznam|repetición|rediffusion|differita)(?!\p{L})\)?/iu';

    /** Виды спорта, которых у нас нет и с которыми путается футбол: «College Football» — американский. */
    private const string IGNORED = '/(?<!\p{L})(college football|american football|fútbol americano|football américain|nfl|ncaa|cfl|onefa|cricket|крикет|baseball|бейсбол|béisbol|beisbol|mlb)(?!\p{L})/iu';

    /** Передачи о спорте, а не трансляции. */
    private const string SHOWS = '/(?<!\p{L})(обзор|превью|шоу|реакция|выпуск|яркие матчи|лучшие моменты|анонс|журнал|студия|новости|классика|иконы|итоги|подкаст|реалити|наши чемпионы|магия|спорт\.док|ностальжи|величайш|все или ничего|тактика|видео дня|перед матчем|после матча|ток-шоу|эксклюзив|тревел|подробно|трансферы|гала-концерт|лучшие|легенды|неделя|история|документальн|уточняется|felvezető|értékelő|összefoglaló|magazin|magazín|highlights|sestřih|zprávy|hírek|noticias|resumen|rewind|epic moments|best of|all access|documentary|dokument|tonight|pregame|postgame|pre-game|post-game|countdown|recap|press conference|paddock|pit walk)/iu';

    /** Сегменты без смысла: качество, «премьера», «сегодня». */
    private const array FILLERS = ['hd', 'sd', 'uhd', 'fhd', '4k', 'premier', 'premiera', 'premiéra', 'mai', 'dnes'];

    /** Сегменты, которые ничего не говорят о событии. */
    private const string NOISE = '/^(трансляция|из\s|сезон\s|в перерыв|спорт-центр|овертайм)/iu';

    private const string ROUND = '/^(\d+(-й|-го)?\s*(тур|этап|круг|раунд)|(тур|этап|етап|круг)\s*\d+.*|\d+\/\d+\s*финала.*|финал(ы| четырёх| четырех)?|döntők|döntő|полуфинал|четвертьфинал|отбор|квалификация|гонка(\s*\d+)?|практика(\s*\d+)?|\d-я практика|свободная практика(\s*\d+)?|спринт|спринт-квалификация|матч\s*\d+|main card|основной кард|предварительный кард|групповой этап.*|общий этап.*|плей-офф.*|.*\sраунд|командные соревнования.*|základní (část|skupina)|ligová fáze|přátelské utkání|skupina \p{L}|\d+\.\s*kolo|kolo \d+|(osmi|čtvrt|semi)?finále|csoportkör|rájátszás.*|\S*elődöntő|negyeddöntő|\d+\.\s*forduló|felkészülési mérkőzés|etapa \S+|et\d+|fecha \d+|jornada \d+|temporada regular.*|matchday.*|week \d+|game \d+|regular season|play-?offs?|(semi-?|quarter-?)?finals?|group stage.*|\d+e journée|journée \d+|giornata \d+|\d+\. spieltag|[sq]f g\d+)$/iu';

    /** Стадия, приклеенная к хозяевам: «základní část Bílí Tygři Liberec - Frölunda». */
    private const string LEADING_ROUND = '/^((základní (část|skupina)|ligová fáze|přátelské utkání|play-?off|dnešní|(osmi|čtvrt|semi)?finále|skupina \p{Lu})\s+)+/iu';

    /** «A vs. B», «A v B»; «A at B» — гости первыми (США). */
    private const string VERSUS = '/\s+(?:vs\.?|v)\s+/u';

    private const string AT = '/\s+at\s+/u';

    private const array GENDERS = ['мужчины', 'женщины', 'жінки', 'чоловіки', 'юниорки', 'юниоры', 'férfiak', 'nők', 'muži', 'ženy', 'men', 'women', "men's", "women's", 'masculin', 'feminin', 'masculino', 'femenino', 'hommes', 'femmes', 'herren', 'damen'];

    private const array COUNTRIES = [
        'россия' => 'RU', 'германия' => 'DE', 'испания' => 'ES', 'англия' => 'GB', 'шотландия' => 'GB',
        'италия' => 'IT', 'франция' => 'FR', 'нидерланды' => 'NL', 'греция' => 'GR', 'австралия' => 'AU',
        'беларусь' => 'BY', 'україна' => 'UA', 'украина' => 'UA', 'саудовская аравия' => 'SA',
        'казахстан' => 'KZ', 'турция' => 'TR', 'сша' => 'US', 'португалия' => 'PT',
    ];

    /** Турниры, по которым понятен вид спорта, если его нет в названии: «Верона – Ареццо. Итальянская Серия Б». */
    private const string FOOTBALL_HINTS = '/(серия [аaб]|премьер-лига|чемпионшип|кубок лиги|ла лига|бундеслига|лига 1|лига наций|апл|liga expansión|liga mx|primera división|primera a|brasileir|serie a|premier league|la liga|bundesliga|ligue 1|eredivisie|copa libertadores|copa sudamericana|liga národů|nations league|nemzetek ligája)/iu';

    private const array ABBREVIATIONS = ['Чемп.' => 'Чемпионат', 'чемп.' => 'чемпионат', 'Смешан.' => 'Смешанные'];

    private const int MAX_TEAM_LENGTH = 50;

    private const array NO_TEAMS = ['motorsport', 'cycling', 'winter', 'biathlon'];

    /**
     * @param  string|null  $sportHint  вид спорта канала (SportRegistry::hint) — если в названии его нет
     */
    public function parse(string $raw, ?CarbonImmutable $airsAt = null, ?string $sportHint = null): ?ParsedTitle
    {
        $text = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
        $live = false;
        $replay = false;

        foreach (self::LIVE_MARKERS as $marker) {
            if (str_starts_with($text, $marker)) {
                $live = true;
                $text = trim(mb_substr($text, mb_strlen($marker)));
            }
        }

        if (preg_match(self::SHOWS, $text) || preg_match(self::IGNORED, $text)) {
            return null;
        }

        if (preg_match(self::LIVE_PHRASES, $text)) {
            $live = true;
            $text = (string) preg_replace(self::LIVE_PHRASES, '', $text);
        }

        // «(04.10.2026)» — дата матча; если она раньше эфира, это запись.
        if (preg_match('/\((\d{2})\.(\d{2})\.(\d{4})\)/', $text, $date)) {
            $played = CarbonImmutable::create((int) $date[3], (int) $date[2], (int) $date[1]);
            $replay = $airsAt !== null && $played !== null && $played->lt($airsAt->startOfDay());
            $text = str_replace($date[0], '', $text);
        }

        if (preg_match(self::REPLAY_PHRASES, $text)) {
            $replay = true;
            $text = (string) preg_replace(self::REPLAY_PHRASES, '', $text);
        }

        // Canal+: «Bordeaux-Bègles / Lyon. Top 14» — пара через косую черту в начале.
        $text = (string) preg_replace('/^([^\/.:,]{2,40}) \/ ([^\/.:,]{2,40})(?=\.|$)/u', '$1 – $2', $text);

        // «Повтор от 7 июля».
        if (preg_match('/[.,]?\s*Повтор(\s+от\s+[^.,]+)?/iu', $text, $repeat)) {
            $replay = true;
            $text = str_replace($repeat[0], '', $text);
        }

        // Часть записи: «1–3 c», «5 с.».
        if (preg_match('/[,.]?\s*\d+(\s*[–-]\s*\d+)?\s*[cс]\.?$/u', $text, $part)) {
            $replay = true;
            $text = mb_substr($text, 0, -mb_strlen($part[0]));
        }

        $text = strtr($text, self::ABBREVIATIONS);
        // «ЛЧ» — Лига чемпионов; «КХЛ.Барыс» — точка без пробела.
        $text = (string) preg_replace(['/(?<!\p{L})ЛЧ(?!\p{L})/u', '/(\p{Lu}{2,})\.(?=\p{Lu})/u'], ['Лига чемпионов', '$1. '], $text);
        $text = (string) preg_replace(['/[.,]?\s*Сезон\s+\d{2,4}(\/\d{2,4})?/iu', '/\(\d{1,2}\+\)/u', '/\.?\s*В перерыв.*$/iu', '/\s+mérkőzés\.?$/u', '/\s{2,}/u'], ['', '', '', '', ' '], $text);

        $segments = array_values(array_filter(array_map(
            fn (string $segment) => trim($segment, " .\t\n"),
            preg_split($this->separators(), trim($text, ' .')) ?: [],
        )));

        return $this->fromSegments($segments, $live, $replay, $sportHint);
    }

    /**
     * @param  list<string>  $segments
     */
    private function fromSegments(array $segments, bool $live, bool $replay, ?string $sportHint): ?ParsedTitle
    {
        $sport = null;
        $competition = null;
        $country = null;
        $countryName = null;
        $teams = null;
        $rounds = [];
        $genders = [];
        $rest = [];

        foreach ($segments as $segment) {
            // «Handbal feminin», «Basketball Women» — вид спорта и пол.
            if (preg_match('/^(.+)\s+(\S+)$/u', $segment, $gendered) && in_array(mb_strtolower($gendered[2]), self::GENDERS, true)
                && ($match = SportRegistry::match($gendered[1])) !== null && ($sport === null || $match['sport'] === $sport)) {
                $sport ??= $match['sport'];
                $genders[] = $gendered[2];

                continue;
            }

            $match = SportRegistry::match($segment);
            $lower = mb_strtolower($segment);

            if ($match !== null && ($sport === null || $match['sport'] === $sport)) {
                $sport ??= $match['sport'];
                if ($match['competition']) {
                    $competition ??= $segment;
                }

                continue;
            }

            if (preg_match(self::NOISE, $segment) || in_array($lower, self::FILLERS, true)) {
                continue;
            }

            if ($teams === null && ($pair = $this->matchup($segment)) !== null) {
                $teams = $pair;

                continue;
            }

            match (true) {
                (bool) preg_match(self::ROUND, $segment) => $rounds[] = $segment,
                isset(self::COUNTRIES[$lower]) => [$country, $countryName] = [$country ?? self::COUNTRIES[$lower], $countryName ?? $segment],
                in_array($lower, self::GENDERS, true) => $genders[] = $segment,
                default => $rest[] = $segment,
            };
        }

        // Setanta пишет пары и через дефис без пробелов: «Ливерпуль-Фулхэм».
        if ($teams === null && $rest !== [] && ($sport !== null || $competition !== null)) {
            $last = end($rest);
            if (preg_match('/^(\p{Lu}[\p{L}\d .]{2,30})-(\p{Lu}[\p{L}\d .]{2,30})$/u', $last, $pair)) {
                array_pop($rest);
                $teams = ['home' => $this->team($pair[1]), 'away' => $this->team($pair[2]), 'competition' => null, 'round' => null];
            }
        }

        if ($sport === null && $teams !== null && preg_match(self::FOOTBALL_HINTS, implode(' ', $rest))) {
            $sport = 'football';
        }

        if ($sport === null && $teams !== null) {
            $sport = $sportHint;
        }

        if ($sport === null) {
            return null;
        }

        // В гонках соперников нет: «Szingapúri nagydíj - Futam» — это гран-при и сессия.
        if ($teams !== null && in_array($sport, self::NO_TEAMS, true)) {
            array_unshift($rest, $teams['home']);
            $rounds[] = $teams['away'];
            $teams = null;
        }

        if ($teams !== null && $teams['round'] !== null) {
            $rounds[] = $teams['round'];
        }

        $round = $rounds !== [] ? implode(' · ', $rounds) : null;

        if ($teams !== null) {
            $competition ??= $teams['competition'] ?? (implode(' · ', [...$rest, ...$genders]) ?: $countryName);

            return new ParsedTitle($sport, "{$teams['home']} — {$teams['away']}", $teams['home'], $teams['away'], $competition, $country, $round, $live, $replay);
        }

        $competition ??= array_shift($rest) ?? $countryName;
        if ($competition === null) {
            return null;
        }

        if ($genders !== []) {
            $competition .= ' · '.implode(' · ', $genders);
        }

        $title = $rest !== [] ? implode('. ', $rest) : $competition;

        return new ParsedTitle($sport, $title, null, null, $competition, $country, $round, $live, $replay);
    }

    /**
     * Где резать название на сегменты: «. » после слова (не после инициала
     * «Д. Кун»), после цифры или скобки; «, », «: », « / ». И перед видом
     * спорта даже после сокращения: «Боруссия Д. Футбол. Чемпионат Германии».
     */
    private function separators(): string
    {
        $sports = implode('|', array_map(fn (string $keyword) => preg_quote($keyword, '/'), SportRegistry::keywords()));

        return '/(?<=[^\s.]{3}|\d|\)|")\.\s+|\.\s+(?=(?:'.$sports.')(?:[.,:]|$))|,\s+|:\s+|\s+\/\s+/iu';
    }

    /**
     * «Динамо (Минск) – СКА», «Erste Liga - DVTK - DEAC», «Силва – Делгадо – Main Card»,
     * «Anadolu Efes vs. Real Madrid», «Baltimore v Milwaukee - SF G1», «Atlanta Dream at Washington Mystics».
     *
     * @return array{home: string, away: string, competition: ?string, round: ?string}|null
     */
    private function matchup(string $segment): ?array
    {
        [$home, $away, $competition, $round] = $this->versus($segment) ?? $this->dashed($segment);
        $home = $home !== null ? trim((string) preg_replace(self::LEADING_ROUND, '', $home)) : null;

        if (! $this->isTeam($home) || ! $this->isTeam($away)) {
            return null;
        }

        return ['home' => $this->team((string) $home), 'away' => $this->team((string) $away), 'competition' => $competition, 'round' => $round];
    }

    /**
     * «vs», «v» и «at»; лишнее через тире по краям — турнир и стадия:
     * «Men - USA vs China», «Milwaukee v Empire - QF G1».
     *
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}|null
     */
    private function versus(string $segment): ?array
    {
        foreach ([self::VERSUS => false, self::AT => true] as $pattern => $awayFirst) {
            $sides = preg_split($pattern, $segment) ?: [];
            if (count($sides) !== 2) {
                continue;
            }

            $before = preg_split('/\s+[–—-]\s+/u', trim($sides[0])) ?: [];
            $after = preg_split('/\s+[–—-]\s+/u', trim($sides[1])) ?: [];
            [$first, $second] = [(string) array_pop($before), (string) array_shift($after)];

            // «at» — слово, а не разделитель, если с обеих сторон не названия: «Clash at the Coliseum».
            if (! preg_match('/^[\p{Lu}\d]/u', $first) || ! preg_match('/^[\p{Lu}\d]/u', $second)) {
                return null;
            }

            [$home, $away] = $awayFirst ? [$second, $first] : [$first, $second];

            return [$home, $away, $before !== [] ? implode(' · ', $before) : null, $after !== [] ? implode(' · ', $after) : null];
        }

        return null;
    }

    /**
     * Через тире: «Хозяева – Гости», «Турнир - Хозяева - Гости», «Хозяева - Гости - Стадия».
     *
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string}
     */
    private function dashed(string $segment): array
    {
        $parts = array_map('trim', preg_split('/\s+[–—-]\s*|\s*[–—]\s+/u', $segment) ?: []);

        return match (count($parts)) {
            // «Világbajnokság - Döntők» — турнир и стадия, а не соперники.
            2 => preg_match(self::ROUND, $parts[1]) ? [null, null, null, null] : [$parts[0], $parts[1], null, null],
            3 => preg_match(self::ROUND, $parts[2])
                ? [$parts[0], $parts[1], null, $parts[2]]
                : [$parts[1], $parts[2], $parts[0], null],
            default => [null, null, null, null],
        };
    }

    private function isTeam(?string $name): bool
    {
        return $name !== null
            && mb_strlen($name) >= 2
            && mb_strlen($name) <= self::MAX_TEAM_LENGTH
            && preg_match('/\p{L}/u', $name)
            // «1975 - 1980 гг» — годы, а не соперники.
            && ! preg_match('/^\d{4}/', $name);
    }

    private function team(string $name): string
    {
        // «Anadolu Efes (Tur)» — код страны, «Duke (1.17.26)» — дата записи.
        $name = (string) preg_replace(['/\s*\((?:[A-Z][a-z]{2}|[A-Z]{3})\)$/u', '/\s*\(\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}\)$/u'], '', $name);

        return trim((string) preg_replace(['/["«»“”„]/u', '/\s{2,}/u'], ['', ' '], $name));
    }
}
