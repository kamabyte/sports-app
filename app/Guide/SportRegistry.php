<?php

namespace App\Guide;

use App\Guide\Data\Sport;
use Illuminate\Support\Collection;

/**
 * Виды спорта и слова, по которым их узнают в телегиде. Слово из
 * COMPETITION_KEYWORDS — сразу и вид спорта, и турнир («КХЛ», «Формула-1»).
 */
final class SportRegistry
{
    /**
     * @var array<string, array{name: string, emoji: string, color: string, keywords: list<string>}>
     */
    private const array SPORTS = [
        'football' => ['name' => 'Футбол', 'emoji' => '⚽', 'color' => '#22c55e', 'keywords' => ['футбол', 'labdarúgás', 'futbolas', 'fotbal', 'futbal', 'fudbal', 'nogomet', 'fútbol', 'futbol', 'futebol', 'calcio', 'fußball', 'fussball', 'voetbal', 'piłka nożna', 'football', 'soccer', 'mls', 'nwsl']],
        'hockey' => ['name' => 'Хоккей', 'emoji' => '🏒', 'color' => '#38bdf8', 'keywords' => ['хоккей', 'кхл', 'мхл', 'вхл', 'нхл', 'jégkorong', 'ledo ritulys', 'lední hokej', 'ľadový hokej', 'hokej', 'hokej na ledu', 'ice hockey', 'hockey', 'eishockey', 'hockey sur glace', 'hochei', 'nhl', 'chl']],
        'basketball' => ['name' => 'Баскетбол', 'emoji' => '🏀', 'color' => '#f97316', 'keywords' => ['баскетбол', 'баскетбол 3х3', 'баскетбол 3x3', 'kosárlabda', 'krepšinis', 'basketbal', 'basketball', 'košarka', 'baschet', 'baloncesto', 'básquetbol', 'basquetbol', 'basketbol', 'pallacanestro', 'koszykówka', 'nba', 'wnba', 'euroleague', 'euroliga', 'eurocup']],
        'volleyball' => ['name' => 'Волейбол', 'emoji' => '🏐', 'color' => '#eab308', 'keywords' => ['волейбол', 'пляжный волейбол', 'röplabda', 'volejbal', 'volleyball', 'volei', 'voleibol', 'pallavolo', 'odbojka', 'siatkówka']],
        'handball' => ['name' => 'Гандбол', 'emoji' => '🤾', 'color' => '#8b5cf6', 'keywords' => ['гандбол', 'kézilabda', 'házená', 'hádzaná', 'handball', 'handbal', 'rukomet', 'balonmano', 'pallamano', 'piłka ręczna']],
        'futsal' => ['name' => 'Футзал', 'emoji' => '🥅', 'color' => '#10b981', 'keywords' => ['футзал', 'мини-футбол', 'futsal']],
        'tennis' => ['name' => 'Теннис', 'emoji' => '🎾', 'color' => '#a3e635', 'keywords' => ['теннис', 'tenisz', 'tenis', 'teniss', 'tenisas', 'tennis', 'atp', 'атр', 'wta']],
        'bandy' => ['name' => 'Хоккей с мячом', 'emoji' => '🏑', 'color' => '#60a5fa', 'keywords' => ['хоккей с мячом']],
        'motorsport' => ['name' => 'Автоспорт', 'emoji' => '🏎️', 'color' => '#ef4444', 'keywords' => ['автоспорт', 'формула-1', 'формула 1', 'forma-1', 'formula 1', 'formula one', 'formule 1', 'fórmula 1', 'f1', 'мотоспорт', 'мотогонки']],
        'combat' => ['name' => 'Единоборства', 'emoji' => '🥊', 'color' => '#e11d48', 'keywords' => ['бокс', 'мма', 'смешанные единоборства', 'смешан. единоборства', 'дзюдо', 'тайский бокс', 'кикбоксинг', 'борьба', 'judo', 'ufc', 'boxing', 'ökölvívás', 'mma']],
        'rugby' => ['name' => 'Регби', 'emoji' => '🏉', 'color' => '#14b8a6', 'keywords' => ['регби', 'rugby', 'ragbi', 'top 14']],
        'waterpolo' => ['name' => 'Водное поло', 'emoji' => '🤽', 'color' => '#0ea5e9', 'keywords' => ['водное поло', 'vízilabda', 'vodní pólo', 'water polo', 'waterpolo', 'vaterpolo', 'pallanuoto']],
        'winter' => ['name' => 'Зимние виды', 'emoji' => '⛷️', 'color' => '#93c5fd', 'keywords' => ['фигурное катание', 'прыжки на лыжах с трамплина', 'лыжные гонки']],
        'biathlon' => ['name' => 'Биатлон', 'emoji' => '🎯', 'color' => '#7dd3fc', 'keywords' => ['биатлон', 'biathlon', 'biatlon', 'biatlonas']],
        'cycling' => ['name' => 'Велоспорт', 'emoji' => '🚴', 'color' => '#06b6d4', 'keywords' => ['велоспорт']],
        'billiards' => ['name' => 'Бильярд', 'emoji' => '🎱', 'color' => '#64748b', 'keywords' => ['бильярд']],
        'esports' => ['name' => 'Киберспорт', 'emoji' => '🎮', 'color' => '#a855f7', 'keywords' => ['киберспорт']],
        'other' => ['name' => 'Другие', 'emoji' => '🏅', 'color' => '#f59e0b', 'keywords' => ['плавание', 'úszás', 'стендовая стрельба', 'гребной слалом', 'настольный теннис', 'бадминтон', 'лёгкая атлетика', 'легкая атлетика', 'художественная гимнастика', 'спортивная гимнастика', 'прыжки на батуте']],
    ];

    /** Командные: событие без команд у них — не событие («Чемпионат КХЛ»). */
    private const array TEAM_SPORTS = ['football', 'hockey', 'basketball', 'volleyball', 'handball', 'futsal', 'bandy', 'rugby', 'waterpolo'];

    /** Слова, которые называют и вид спорта, и турнир: сегмент «КХЛ» — это хоккей, турнир КХЛ. */
    private const array COMPETITION_KEYWORDS = ['кхл', 'мхл', 'вхл', 'нхл', 'формула-1', 'формула 1', 'forma-1', 'formula 1', 'formula one', 'formule 1', 'fórmula 1', 'f1', 'atp', 'атр', 'wta', 'ufc', 'nhl', 'chl', 'nba', 'wnba', 'euroleague', 'euroliga', 'eurocup', 'mls', 'nwsl', 'top 14'];

    /** Турнир с видом спорта в названии длиннее не бывает — это уже фраза. */
    private const int MAX_COMPETITION_WORDS = 6;

    /**
     * @return Collection<int, Sport>
     */
    public static function all(): Collection
    {
        return collect(self::SPORTS)
            ->map(fn (array $sport, string $key) => new Sport($key, $sport['name'], $sport['emoji'], $sport['color']))
            ->values();
    }

    public static function isTeamSport(string $key): bool
    {
        return in_array($key, self::TEAM_SPORTS, true);
    }

    /**
     * Передача без соперников — это одно событие турнира: матч командного вида
     * («Чемпионат КХЛ») или турнир UFC целиком. В теннисе слот турнира — много матчей.
     */
    public static function isOneEventPerSlot(string $key): bool
    {
        return self::isTeamSport($key) || $key === 'combat';
    }

    public static function find(string $key): ?Sport
    {
        $sport = self::SPORTS[$key] ?? null;

        return $sport ? new Sport($key, $sport['name'], $sport['emoji'], $sport['color']) : null;
    }

    /**
     * Вид спорта по сегменту названия: «Футбол», «КХЛ», «UFC 332 …».
     *
     * @return array{sport: string, competition: bool}|null
     */
    public static function match(string $segment): ?array
    {
        // «Бокс (Мужчины/Женщины)» — уточнение в скобках вид спорта не меняет.
        $segment = mb_strtolower(trim((string) preg_replace('/\s*\([^)]*\)$/u', '', $segment)));

        foreach (self::SPORTS as $key => $sport) {
            foreach ($sport['keywords'] as $keyword) {
                if ($segment === $keyword) {
                    return ['sport' => $key, 'competition' => in_array($keyword, self::COMPETITION_KEYWORDS, true)];
                }

                // «UFC 332 в Солт-Лейк-Сити», «Чемпионат МХЛ» — турнир со своим названием.
                if (in_array($keyword, self::COMPETITION_KEYWORDS, true) && self::containsWord($segment, $keyword)) {
                    return ['sport' => $key, 'competition' => true];
                }
            }
        }

        // «NWSL Soccer», «Fútbol Colombiano Primera División» — турнир с видом
        // спорта в названии. Не пара соперников: «Hockey Club Davos – Ambri».
        if (count(explode(' ', $segment)) > self::MAX_COMPETITION_WORDS || preg_match('/\s[–—-]\s|\svs?\.?\s|\sat\s/u', $segment)) {
            return null;
        }

        foreach (self::SPORTS as $key => $sport) {
            foreach ($sport['keywords'] as $keyword) {
                if (self::containsWord($segment, $keyword)) {
                    return ['sport' => $key, 'competition' => true];
                }
            }
        }

        return null;
    }

    /**
     * Вид спорта канала по его названию: «Okko Футбол», «КХЛ Prime». Нужен,
     * когда в названии передачи вида спорта нет: «Чжэцзян – Шанхай Порт».
     */
    public static function hint(string $channel): ?string
    {
        $channel = mb_strtolower($channel);

        foreach (self::SPORTS as $key => $sport) {
            foreach ($sport['keywords'] as $keyword) {
                if (self::containsWord($channel, $keyword)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Все слова, которыми называют виды спорта, — для разбора названий.
     *
     * @return list<string>
     */
    public static function keywords(): array
    {
        return array_merge(...array_values(array_map(fn (array $sport) => $sport['keywords'], self::SPORTS)));
    }

    private static function containsWord(string $text, string $word): bool
    {
        return (bool) preg_match('/(?<!\p{L})'.preg_quote($word, '/').'(?!\p{L})/u', $text);
    }
}
