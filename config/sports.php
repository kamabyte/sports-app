<?php

return [

    /*
     * Режим приложения. standalone — самостоятельный спортивный каталог
     * (docs/sports-catalog-reorganization-plan.md): данные только из Flashscore,
     * каналы, телегиды, Dispatcharr и старые источники расписания выключены и
     * ничего не запрашивают. iptv — прежнее приложение «матчи с трансляциями»;
     * возвращать его — отдельное решение владельца.
     */
    'mode' => env('SPORTS_MODE', 'standalone'),

    /*
     * Каталог видов спорта (таблицы catalog_*).
     */
    'catalog' => [
        // Язык названий в интерфейсе; нет перевода — показывается название источника.
        'locale' => 'ru',
        // Данные старше этого считаются устаревшими: интерфейс так и пишет.
        'stale_hours' => (int) env('SPORTS_CATALOG_STALE_HOURS', 6),

        // Виды спорта: ключ → название, семейство (модель события и страница
        // матча), волна охвата и раздел Flashscore. Порядок — порядок в меню.
        'sports' => [
            'football' => ['name' => 'Футбол', 'family' => 'team', 'wave' => 'A', 'flashscore' => 'football'],
            'tennis' => ['name' => 'Теннис', 'family' => 'racket', 'wave' => 'A', 'flashscore' => 'tennis'],
            'basketball' => ['name' => 'Баскетбол', 'family' => 'team', 'wave' => 'A', 'flashscore' => 'basketball'],
            'hockey' => ['name' => 'Хоккей', 'family' => 'team', 'wave' => 'A', 'flashscore' => 'hockey'],
            'volleyball' => ['name' => 'Волейбол', 'family' => 'team', 'wave' => 'B', 'flashscore' => 'volleyball'],
            'handball' => ['name' => 'Гандбол', 'family' => 'team', 'wave' => 'B', 'flashscore' => 'handball'],
            'baseball' => ['name' => 'Бейсбол', 'family' => 'team', 'wave' => 'B', 'flashscore' => 'baseball'],
            'american-football' => ['name' => 'Американский футбол', 'family' => 'team', 'wave' => 'B', 'flashscore' => 'american-football'],
            'rugby-union' => ['name' => 'Регби', 'family' => 'team', 'wave' => 'B', 'flashscore' => 'rugby-union'],
            'rugby-league' => ['name' => 'Регбилиг', 'family' => 'team', 'wave' => 'B', 'flashscore' => 'rugby-league'],
            'cricket' => ['name' => 'Крикет', 'family' => 'cricket', 'wave' => 'B', 'flashscore' => 'cricket'],
            'table-tennis' => ['name' => 'Настольный теннис', 'family' => 'racket', 'wave' => 'B', 'flashscore' => 'table-tennis'],
            'badminton' => ['name' => 'Бадминтон', 'family' => 'racket', 'wave' => 'B', 'flashscore' => 'badminton'],
            'snooker' => ['name' => 'Снукер', 'family' => 'duel', 'wave' => 'B', 'flashscore' => 'snooker'],
            'darts' => ['name' => 'Дартс', 'family' => 'duel', 'wave' => 'B', 'flashscore' => 'darts'],
            'boxing' => ['name' => 'Бокс', 'family' => 'bout', 'wave' => 'B', 'flashscore' => 'boxing'],
            'mma' => ['name' => 'ММА', 'family' => 'bout', 'wave' => 'B', 'flashscore' => 'mma'],
            'esports' => ['name' => 'Киберспорт', 'family' => 'team', 'wave' => 'B', 'flashscore' => 'esports'],
            // Несколько разделов Flashscore на один наш вид спорта: ключ раздела — путь => название группы.
            'motorsport' => ['name' => 'Автоспорт', 'family' => 'race', 'wave' => 'C', 'flashscore' => ['auto-racing' => 'Автогонки', 'moto-racing' => 'Мотогонки']],
            'golf' => ['name' => 'Гольф', 'family' => 'golf', 'wave' => 'C', 'flashscore' => 'golf'],
            'cycling' => ['name' => 'Велоспорт', 'family' => 'race', 'wave' => 'C', 'flashscore' => 'cycling'],
            'winter-sports' => ['name' => 'Зимние виды', 'family' => 'race', 'wave' => 'C', 'flashscore' => ['biathlon' => 'Биатлон', 'alpine-skiing' => 'Горные лыжи', 'cross-country-skiing' => 'Лыжные гонки', 'ski-jumping' => 'Прыжки с трамплина']],
        ],

        /*
         * Flashscore: публичные страницы турниров и команд www.flashscore.com
         * (английские названия) и www.flashscorekz.com (русские). Данные
         * читаются из самой страницы как текст — без подписанных запросов,
         * без выполнения скриптов и без обхода проверок. Условия сайта
         * ограничивают сбор данных: включать регулярный импорт решает владелец.
         */
        'flashscore' => [
            'enabled' => (bool) env('FLASHSCORE_ENABLED', false),
            'hosts' => [
                'en' => 'https://www.flashscore.com',
                'ru' => 'https://www.flashscorekz.com',
            ],
            'pause_seconds' => (float) env('FLASHSCORE_PAUSE_SECONDS', 3),
            'cache_hours' => (int) env('FLASHSCORE_CACHE_HOURS', 3),
            'timeout_seconds' => 20,
            // Плановый запуск (catalog:import без --sport/--page): сколько страниц турниров за раз
            // (каждая — 1–2 запроса), как часто обновлять русские названия.
            'pages_per_run' => (int) env('FLASHSCORE_PAGES_PER_RUN', 15),
            'translate_days' => 7,
            // Дневные списки лёгкой версии (www.flashscore.mobi): расписание на неделю вперёд
            // (сайт отдаёт до 7 дней). Сегодня и завтра — раз в час, дальние дни — раз в 12 часов.
            'lite' => [
                'enabled' => (bool) env('FLASHSCORE_LITE_ENABLED', true),
                'days' => [0, 1, 2, 3, 4, 5, 6, 7],
                'cache_minutes' => 60,
            ],
            // Страницы турниров, которые загружает catalog:import (волна A, ограниченный набор).
            'competitions' => [
                'football' => [
                    '/football/england/premier-league/',
                    '/football/russia/premier-league/',
                    '/football/europe/champions-league/',
                    '/football/europe/uefa-nations-league/',
                ],
                'basketball' => ['/basketball/usa/nba/', '/basketball/europe/euroleague/'],
                'hockey' => ['/hockey/usa/nhl/', '/hockey/russia/khl/'],
                'tennis' => [
                    '/tennis/atp-singles/shanghai/',
                    '/tennis/atp-doubles/shanghai/',
                    '/tennis/wta-singles/beijing/',
                    '/tennis/wta-doubles/beijing/',
                ],
                // Волна B.
                'volleyball' => ['/volleyball/italy/superlega/', '/volleyball/russia/superleague/', '/volleyball/europe/european-championships/'],
                'handball' => ['/handball/germany/bundesliga/', '/handball/europe/champions-league/'],
                'baseball' => ['/baseball/usa/mlb/'],
                'american-football' => ['/american-football/usa/nfl/'],
                'rugby-union' => ['/rugby-union/france/top-14/', '/rugby-union/england/premiership-rugby/'],
                'rugby-league' => ['/rugby-league/australia/nrl/', '/rugby-league/england/super-league/'],
                'cricket' => ['/cricket/north-central-america/caribbean-premier-league/', '/cricket/australia/big-bash-league/'],
                'table-tennis' => ['/table-tennis/others-men/china-smash/', '/table-tennis/others-doubles-men/china-smash/'],
                'badminton' => ['/badminton/bwf-world-tour-men/arctic-open/', '/badminton/bwf-world-tour-women/arctic-open/'],
                'snooker' => ['/snooker/china/shanghai-masters/'],
                'darts' => ['/darts/world/world-grand-prix/'],
                'boxing' => ['/boxing/heavyweight-men/wbo-title/'],
                'mma' => ['/mma/lightweight-men/pfl/'],
                'esports' => ['/esports/counter-strike/esl-pro-league-season-24/', '/esports/league-of-legends/world-championship/'],
                // Волна C.
                'motorsport' => ['/auto-racing/formula-1/azerbaijan-grand-prix/', '/auto-racing/formula-1/singapore-grand-prix/', '/moto-racing/motogp/mandalika/'],
                'golf' => ['/golf/dp-world-tour/bmw-pga-championship/'],
                'cycling' => ['/cycling/tours/vuelta-a-espana/', '/cycling/races/il-lombardia/'],
                'winter-sports' => ['/biathlon/sprint-men/oestersund/', '/alpine-skiing/super-g-men/lake-louise/'],
            ],
            // Профили команд: матчи команды во всех турнирах. national — страница сборной.
            'teams' => [
                'football' => [
                    ['path' => '/team/france/QkGeVG1n/', 'national' => true],
                    ['path' => '/team/france/G0E4tMc9/', 'national' => true],
                    ['path' => '/team/france/ShmZavVI/', 'national' => true],
                ],
            ],
            // Турниры сборных (адреса как у источника): их участники — сборные, а не клубы.
            'national_competitions' => [
                '/football/europe/uefa-nations-league/',
                '/football/europe/euro/',
                '/football/europe/world-championship/',
                '/football/world/world-championship/',
                '/football/world/friendly-international/',
                '/football/world/olympic-games/',
                '/football/europe/euro-u21/',
                '/football/world/world-cup-women/',
                '/football/europe/euro-women/',
                '/football/europe/uefa-nations-league-women/',
                '/football/world/friendly-international-women/',
                '/football/world/olympic-games-women/',
            ],
            // Номер вида спорта у Flashscore (поле SA) — только проверенные на страницах.
            // Страница с другим номером — ошибка настройки, она не импортируется.
            'sport_ids' => [
                'football' => [1], 'tennis' => [2], 'hockey' => [4], 'american-football' => [5], 'baseball' => [6],
                'handball' => [7], 'rugby-union' => [8], 'volleyball' => [12], 'cricket' => [13], 'darts' => [14],
                'snooker' => [15], 'boxing' => [16], 'rugby-league' => [19], 'badminton' => [21], 'golf' => [23],
                'table-tennis' => [25], 'mma' => [28], 'motorsport' => [32, 33], 'cycling' => [34], 'esports' => [36],
                'winter-sports' => [39, 41],
            ],
        ],
    ],

    /*
     * Откуда берутся события: dispatcharr — матчи расписания с показами на
     * каналах Dispatcharr (guide:import, App\Guide\DatabaseEventCatalog); fake —
     * сгенерированное расписание для разработки и тестов.
     */
    'source' => env('SPORTS_SOURCE', 'dispatcharr'),

    /*
     * Часовой пояс зрителя: по нему расписание делится на дни и
     * показывается время (веб получает его в общих пропсах).
     */
    'timezone' => env('SPORTS_TIMEZONE', 'Europe/Moscow'),

    /*
     * Трансляции события сортируются по языку: сначала эти, по порядку.
     */
    'preferred_languages' => ['ru', 'en'],

    /*
     * Источники трансляций. Сейчас один — домашний Dispatcharr (Xtream Codes).
     * Фейковый каталог берёт отсюда только имя для бейджа «где показывают».
     */
    /*
     * Расписание матчей (fixtures:import): к матчам привязываются события
     * телегида — у них появляются точное время, счёт, эмблемы и честные
     * пометки «прямой эфир / повтор».
     */
    'fixtures' => [
        'days_back' => (int) env('FIXTURES_DAYS_BACK', 0),
        // Неделя вперёд: чем больше матчей, тем больше команд с эмблемами.
        'days_ahead' => (int) env('FIXTURES_DAYS_AHEAD', 6),

        // Неофициальные бесплатные источники; при сбое одного остальные грузятся.
        'sources' => [
            // ESPN: путь лиги → наш вид спорта.
            'espn' => [
                'enabled' => (bool) env('ESPN_ENABLED', true),
                'leagues' => [
                    'soccer/rus.1' => 'football',
                    'soccer/eng.1' => 'football',
                    'soccer/esp.1' => 'football',
                    'soccer/ger.1' => 'football',
                    'soccer/ita.1' => 'football',
                    'soccer/fra.1' => 'football',
                    'soccer/ned.1' => 'football',
                    'soccer/por.1' => 'football',
                    'soccer/uefa.champions' => 'football',
                    'soccer/uefa.europa' => 'football',
                    'soccer/uefa.europa.conf' => 'football',
                    'soccer/uefa.nations' => 'football',
                    'hockey/nhl' => 'hockey',
                    'basketball/nba' => 'basketball',
                    // Теннис — одиночные матчи; UFC — турнир целиком по главному
                    // бою; Формула-1 — каждая сессия Гран-при без соперников.
                    'tennis/atp' => 'tennis',
                    'tennis/wta' => 'tennis',
                    'mma/ufc' => 'combat',
                    'racing/f1' => 'motorsport',
                ],
            ],

            // Биатлон: открытый API результатов IBU — Кубок мира и чемпионаты мира.
            'ibu' => [
                'enabled' => (bool) env('IBU_ENABLED', true),
            ],

            // КХЛ: мобильный API, названия по-русски.
            'khl' => [
                'enabled' => (bool) env('KHL_ENABLED', true),
            ],

            // Евролига и Еврокубок: весь сезон одним запросом.
            'euroleague' => [
                'enabled' => (bool) env('EUROLEAGUE_ENABLED', true),
            ],

            // LiveScore: наш вид спорта → вид спорта LiveScore. Волейбола и гандбола нет.
            'livescore' => [
                'enabled' => (bool) env('LIVESCORE_ENABLED', true),
                'sports' => [
                    'football' => 'soccer',
                    'hockey' => 'hockey',
                    'basketball' => 'basketball',
                ],
            ],

            // Flashscore lite (www.flashscore.mobi): HTML-страницы видов спорта, без
            // входа и подписанных запросов. Условия сайта запрещают сбор данных без
            // согласия — выключен по умолчанию, включать решает владелец. Время на
            // сайте — по Праге; дальше завтрашнего дня страницы берутся из кэша.
            'flashscore' => [
                'enabled' => (bool) env('FLASHSCORE_ENABLED', false),
                'timezone' => env('FLASHSCORE_TIMEZONE', 'Europe/Prague'),
                'pause_seconds' => (int) env('FLASHSCORE_PAUSE_SECONDS', 2),
                'cache_hours' => (int) env('FLASHSCORE_CACHE_HOURS', 6),
                // Сайт отдаёт неделю вперёд (?d=7), а сутки UTC захватывают и следующий день сайта.
                'days_ahead' => min(6, (int) env('FLASHSCORE_DAYS_AHEAD', 6)),
                // Наш вид спорта → раздел сайта. Только то, чего мало у API выше.
                'sports' => [
                    'handball' => 'handball',
                    'volleyball' => 'volleyball',
                    'rugby' => 'rugby-union',
                    'futsal' => 'futsal',
                    'bandy' => 'bandy',
                    'waterpolo' => 'water-polo',
                ],
            ],

            // Бесплатный ключ «123» — три матча на вид спорта в день и ~3
            // запроса в минуту (pause_seconds); премиум ($9/мес) — всё.
            'thesportsdb' => [
                'enabled' => (bool) env('THESPORTSDB_ENABLED', true),
                'key' => env('THESPORTSDB_KEY', '123'),
                'pause_seconds' => (int) env('THESPORTSDB_PAUSE_SECONDS', 21),
                // Медленный: только сегодня и завтра.
                'days_ahead' => (int) env('THESPORTSDB_DAYS_AHEAD', 1),
                // Наш вид спорта → название в TheSportsDB.
                // Только то, чего нет у бесплатных источников выше.
                'sports' => [
                    'volleyball' => 'Volleyball',
                    'handball' => 'Handball',
                    'rugby' => 'Rugby',
                ],
            ],
        ],
    ],

    /*
     * Телегиды (guide:import): свои, а не из Dispatcharr. Каждый XMLTV
     * скачивается раз в refresh_hours, каналы привязываются к ним сами — по
     * tvg-id из плейлиста Dispatcharr и по названию (App\Guide\Listings\ChannelMatcher).
     * Порядок источников — приоритет, если канал нашёлся в нескольких.
     */
    'guides' => [
        'refresh_hours' => (int) env('GUIDES_REFRESH_HOURS', 12),
        'days_ahead' => (int) env('GUIDES_DAYS_AHEAD', 7),

        // any_country — телегид каналов без страны (FAST-сервисы): канал с
        // tvg-id другой страны может найтись в нём по названию.
        'sources' => [
            'epg_one' => ['name' => 'EPG One', 'url' => 'http://epg.one/epg2.xml.gz'],
            'plex' => ['name' => 'Plex', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_PLEX1.xml.gz', 'any_country' => true],
            'us' => ['name' => 'США', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_US2.xml.gz'],
            'uk' => ['name' => 'Великобритания', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_UK1.xml.gz'],
            'cz' => ['name' => 'Чехия', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_CZ1.xml.gz'],
            'sk' => ['name' => 'Словакия', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_SK1.xml.gz'],
            'hu' => ['name' => 'Венгрия', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_HU1.xml.gz'],
            'it' => ['name' => 'Италия', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_IT1.xml.gz'],
            'fr' => ['name' => 'Франция', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_FR1.xml.gz'],
            'ro' => ['name' => 'Румыния', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_RO1.xml.gz'],
            'rs' => ['name' => 'Сербия', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_RS1.xml.gz'],
            'co' => ['name' => 'Колумбия', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_CO1.xml.gz'],
            'mx' => ['name' => 'Мексика', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_MX1.xml.gz'],
            'in' => ['name' => 'Индия', 'url' => 'https://epgshare01.online/epgshare01/epg_ripper_IN1.xml.gz'],
            'samsung_us' => ['name' => 'Samsung TV Plus', 'url' => 'https://github.com/matthuisman/i.mjh.nz/raw/master/SamsungTVPlus/us.xml.gz', 'any_country' => true],
            'roku' => ['name' => 'Roku', 'url' => 'https://github.com/matthuisman/i.mjh.nz/raw/master/Roku/all.xml', 'any_country' => true],
        ],

        // Привязка вручную, если автоматическая ошиблась: название канала →
        // «источник:id канала в телегиде»; null — канал без телегида.
        'channels' => [],
    ],

    'providers' => [
        'dispatcharr' => [
            'name' => env('DISPATCHARR_NAME', 'Dispatcharr'),
            'url' => env('DISPATCHARR_URL', 'http://iptv.home.internal'),
            'username' => env('DISPATCHARR_USERNAME'),
            'password' => env('DISPATCHARR_PASSWORD'),
            // Язык каналов по категории Dispatcharr: у трансляций на нём — приоритет.
            'category_languages' => ['Спорт' => 'ru'],
        ],
    ],

];
