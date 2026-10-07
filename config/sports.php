<?php

return [

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
