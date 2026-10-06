<?php

namespace App\Guide\Fake;

/**
 * Справочник для фейкового каталога: виды спорта, турниры, команды, каналы
 * и недельная сетка (время — UTC). Сетка плотная, чтобы в любое время суток
 * что-то шло в эфире: днём — теннис из Азии, КХЛ, футбол; ночью — НХЛ и НБА.
 */
final class Fixtures
{
    /**
     * @return array<string, array{name: string, emoji: string, color: string}>
     */
    public static function sports(): array
    {
        return [
            'football' => ['name' => 'Футбол', 'emoji' => '⚽', 'color' => '#22c55e'],
            'hockey' => ['name' => 'Хоккей', 'emoji' => '🏒', 'color' => '#38bdf8'],
            'basketball' => ['name' => 'Баскетбол', 'emoji' => '🏀', 'color' => '#f97316'],
            'tennis' => ['name' => 'Теннис', 'emoji' => '🎾', 'color' => '#a3e635'],
            'motorsport' => ['name' => 'Автоспорт', 'emoji' => '🏎️', 'color' => '#ef4444'],
            'mma' => ['name' => 'Единоборства', 'emoji' => '🥊', 'color' => '#e11d48'],
        ];
    }

    /**
     * Каналы: название, номер в плейлисте, страна, язык, качество.
     *
     * @return array<string, array{0: string, 1: int, 2: string, 3: string, 4: string}>
     */
    public static function channels(): array
    {
        return [
            'match-tv' => ['Матч ТВ', 200, 'RU', 'ru', 'FHD'],
            'match-football1' => ['Матч! Футбол 1', 201, 'RU', 'ru', 'HD'],
            'match-football2' => ['Матч! Футбол 2', 202, 'RU', 'ru', 'HD'],
            'match-premier' => ['Матч Премьер', 203, 'RU', 'ru', 'FHD'],
            'match-arena' => ['Матч! Арена', 204, 'RU', 'ru', 'HD'],
            'match-fighter' => ['Матч! Боец', 205, 'RU', 'ru', 'HD'],
            'khl-hd' => ['КХЛ HD', 206, 'RU', 'ru', 'FHD'],
            'okko-sport' => ['Okko Спорт', 207, 'RU', 'ru', '4K'],
            'setanta1' => ['Setanta Sports 1', 210, 'KZ', 'ru', 'HD'],
            'setanta2' => ['Setanta Sports 2', 211, 'KZ', 'ru', 'HD'],
            'sky-pl' => ['Sky Sports Premier League', 101, 'GB', 'en', 'FHD'],
            'sky-main' => ['Sky Sports Main Event', 102, 'GB', 'en', '4K'],
            'sky-f1' => ['Sky Sports F1', 103, 'GB', 'en', '4K'],
            'tnt1' => ['TNT Sports 1', 110, 'GB', 'en', 'FHD'],
            'bein1' => ['beIN Sports 1', 301, 'QA', 'ar', 'FHD'],
            'bein2' => ['beIN Sports 2', 302, 'QA', 'ar', 'HD'],
            'dazn1' => ['DAZN 1', 401, 'DE', 'de', 'FHD'],
            'movistar-ll' => ['Movistar LaLiga', 402, 'ES', 'es', 'FHD'],
            'sky-calcio' => ['Sky Sport Calcio', 403, 'IT', 'it', 'FHD'],
            'canal-sport' => ['Canal+ Sport', 404, 'FR', 'fr', 'HD'],
            'espn' => ['ESPN', 601, 'US', 'en', 'FHD'],
            'espn2' => ['ESPN 2', 602, 'US', 'en', 'HD'],
            'tsn1' => ['TSN 1', 603, 'CA', 'en', 'FHD'],
            'sportsnet' => ['Sportsnet ONE', 604, 'CA', 'en', 'HD'],
            'nba-tv' => ['NBA TV', 605, 'US', 'en', 'FHD'],
            'tnt-us' => ['TNT', 606, 'US', 'en', 'HD'],
            'eurosport1' => ['Eurosport 1', 701, 'EU', 'en', 'HD'],
            'eurosport2' => ['Eurosport 2', 702, 'EU', 'en', 'HD'],
            'tennis-channel' => ['Tennis Channel', 703, 'US', 'en', 'FHD'],
            'ufc-fight-pass' => ['UFC Fight Pass', 801, 'US', 'en', 'FHD'],
        ];
    }

    /**
     * Турниры. schedule — время начала по дням недели (ISO: 1 — понедельник),
     * повтор времени — несколько матчей одновременно. sessions — для турниров
     * без соперников (Формула-1): время, название сессии, длительность.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function competitions(): array
    {
        return [
            'ucl' => [
                'sport' => 'football', 'name' => 'Лига чемпионов', 'short' => 'ЛЧ', 'country' => 'EU',
                'weight' => 10, 'duration' => 115, 'round' => 'league-phase',
                'channels' => ['match-premier', 'okko-sport', 'tnt1', 'bein1', 'canal-sport', 'dazn1'],
                'schedule' => [2 => ['16:45', '16:45', '19:00', '19:00', '19:00', '19:00'], 3 => ['16:45', '16:45', '19:00', '19:00', '19:00', '19:00']],
                'teams' => [
                    ['Реал Мадрид', 'RMA', '#febe10'], ['Барселона', 'BAR', '#a50044'], ['Бавария', 'BAY', '#dc052d'],
                    ['ПСЖ', 'PSG', '#004170'], ['Интер', 'INT', '#0068a8'], ['Манчестер Сити', 'MCI', '#6cabdd'],
                    ['Арсенал', 'ARS', '#ef0107'], ['Ливерпуль', 'LIV', '#c8102e'], ['Боруссия Д', 'BVB', '#fde100'],
                    ['Бенфика', 'BEN', '#e83030'], ['Атлетико', 'ATM', '#cb3524'], ['Наполи', 'NAP', '#12a0d7'],
                    ['Ювентус', 'JUV', '#2b2b2b'], ['Байер', 'LEV', '#e32221'], ['Порту', 'POR', '#00428c'], ['Аякс', 'AJA', '#d2122e'],
                ],
            ],
            'uel' => [
                'sport' => 'football', 'name' => 'Лига Европы', 'short' => 'ЛЕ', 'country' => 'EU',
                'weight' => 7, 'duration' => 115, 'round' => 'league-phase',
                'channels' => ['match-football1', 'okko-sport', 'tnt1', 'bein2', 'canal-sport'],
                'schedule' => [4 => ['16:45', '16:45', '16:45', '19:00', '19:00', '19:00']],
                'teams' => [
                    ['Тоттенхэм', 'TOT', '#132257'], ['Рома', 'ROM', '#8e1f2f'], ['Лацио', 'LAZ', '#87d8f7'],
                    ['Айнтрахт', 'SGE', '#e1000f'], ['Лион', 'OL', '#14387f'], ['Марсель', 'OM', '#2faee0'],
                    ['Фенербахче', 'FEN', '#ffed00'], ['Галатасарай', 'GAL', '#a90432'], ['Бетис', 'BET', '#00954c'],
                    ['Селтик', 'CEL', '#018749'], ['Рейнджерс', 'RAN', '#1b458f'], ['Порту', 'POR', '#00428c'],
                ],
            ],
            'epl' => [
                'sport' => 'football', 'name' => 'Премьер-лига', 'short' => 'АПЛ', 'country' => 'GB',
                'weight' => 9, 'duration' => 115, 'round' => 'matchday',
                'channels' => ['match-premier', 'okko-sport', 'sky-pl', 'sky-main', 'tnt1', 'bein1', 'setanta1'],
                'schedule' => [6 => ['11:30', '14:00', '14:00', '14:00', '16:30'], 7 => ['13:00', '13:00', '15:30'], 1 => ['19:00']],
                'teams' => [
                    ['Арсенал', 'ARS', '#ef0107'], ['Челси', 'CHE', '#034694'], ['Ливерпуль', 'LIV', '#c8102e'],
                    ['Манчестер Сити', 'MCI', '#6cabdd'], ['Манчестер Юнайтед', 'MUN', '#da291c'], ['Тоттенхэм', 'TOT', '#132257'],
                    ['Ньюкасл', 'NEW', '#241f20'], ['Астон Вилла', 'AVL', '#670e36'], ['Брайтон', 'BHA', '#0057b8'],
                    ['Вест Хэм', 'WHU', '#7a263a'], ['Эвертон', 'EVE', '#003399'], ['Ноттингем Форест', 'NFO', '#dd0000'],
                ],
            ],
            'laliga' => [
                'sport' => 'football', 'name' => 'Ла Лига', 'short' => 'Ла Лига', 'country' => 'ES',
                'weight' => 8, 'duration' => 115, 'round' => 'matchday',
                'channels' => ['match-football1', 'okko-sport', 'movistar-ll', 'bein2', 'setanta2'],
                'schedule' => [5 => ['19:00'], 6 => ['12:00', '14:15', '16:30', '19:00'], 7 => ['12:00', '14:15', '16:30', '19:00']],
                'teams' => [
                    ['Реал Мадрид', 'RMA', '#febe10'], ['Барселона', 'BAR', '#a50044'], ['Атлетико', 'ATM', '#cb3524'],
                    ['Севилья', 'SEV', '#d71920'], ['Вильярреал', 'VIL', '#e5c000'], ['Реал Сосьедад', 'RSO', '#0067b1'],
                    ['Бетис', 'BET', '#00954c'], ['Атлетик', 'ATH', '#ee2523'], ['Валенсия', 'VAL', '#ee3524'], ['Жирона', 'GIR', '#cd2534'],
                ],
            ],
            'seriea' => [
                'sport' => 'football', 'name' => 'Серия А', 'short' => 'Серия А', 'country' => 'IT',
                'weight' => 7, 'duration' => 115, 'round' => 'matchday',
                'channels' => ['match-football2', 'sky-calcio', 'dazn1', 'bein2'],
                'schedule' => [6 => ['13:00', '16:00', '18:45'], 7 => ['10:30', '13:00', '16:00', '18:45']],
                'teams' => [
                    ['Интер', 'INT', '#0068a8'], ['Милан', 'MIL', '#fb090b'], ['Ювентус', 'JUV', '#2b2b2b'], ['Наполи', 'NAP', '#12a0d7'],
                    ['Рома', 'ROM', '#8e1f2f'], ['Лацио', 'LAZ', '#87d8f7'], ['Аталанта', 'ATA', '#1e71b8'], ['Фиорентина', 'FIO', '#482e92'],
                ],
            ],
            'rpl' => [
                'sport' => 'football', 'name' => 'РПЛ', 'short' => 'РПЛ', 'country' => 'RU',
                'weight' => 8, 'duration' => 115, 'round' => 'matchday',
                'channels' => ['match-premier', 'match-tv', 'okko-sport', 'match-football1'],
                'schedule' => [5 => ['16:30'], 6 => ['11:00', '13:30', '16:00', '18:30'], 7 => ['11:00', '13:30', '16:00', '18:30'], 1 => ['16:30']],
                'teams' => [
                    ['Зенит', 'ZEN', '#0090d0'], ['Спартак', 'SPA', '#d8282f'], ['ЦСКА', 'CSK', '#003f87'], ['Локомотив', 'LOK', '#e2001a'],
                    ['Динамо', 'DIN', '#0055a5'], ['Краснодар', 'KRA', '#00a651'], ['Ростов', 'ROS', '#e5b800'], ['Рубин', 'RUB', '#8b1e3f'],
                    ['Ахмат', 'AKH', '#00843d'], ['Крылья Советов', 'KRY', '#1e5aa8'], ['Акрон', 'AKR', '#3a3a3a'], ['Пари НН', 'PNN', '#1d3c8f'],
                ],
            ],
            'khl' => [
                'sport' => 'hockey', 'name' => 'КХЛ', 'short' => 'КХЛ', 'country' => 'RU',
                'weight' => 7, 'duration' => 150, 'round' => 'regular',
                'channels' => ['khl-hd', 'match-tv', 'match-arena', 'okko-sport'],
                'schedule' => [
                    1 => ['16:30', '16:30'], 2 => ['09:00', '14:00', '16:30', '16:30', '16:30'], 3 => ['16:30', '16:30', '16:30'],
                    4 => ['09:00', '14:00', '16:30', '16:30', '16:30'], 5 => ['16:30', '16:30'], 6 => ['11:00', '14:00', '16:30'], 7 => ['11:00', '14:00', '16:30'],
                ],
                'teams' => [
                    ['СКА', 'SKA', '#0d3b8e'], ['ЦСКА', 'CSK', '#c8102e'], ['Металлург Мг', 'MMG', '#00539b'], ['Ак Барс', 'AKB', '#009a44'],
                    ['Авангард', 'AVG', '#b41f2e'], ['Локомотив', 'LOK', '#d52b1e'], ['Динамо М', 'DIN', '#003da5'], ['Трактор', 'TRK', '#2b2b2b'],
                    ['Салават Юлаев', 'SAL', '#00843d'], ['Спартак', 'SPA', '#b5121b'], ['Северсталь', 'SEV', '#e30613'], ['Торпедо', 'TOR', '#003366'],
                ],
            ],
            'nhl' => [
                'sport' => 'hockey', 'name' => 'НХЛ', 'short' => 'НХЛ', 'country' => 'US',
                'weight' => 8, 'duration' => 150, 'round' => 'regular',
                'channels' => ['match-arena', 'espn', 'tsn1', 'sportsnet', 'tnt-us'],
                'schedule' => array_fill(1, 7, ['00:00', '00:00', '02:00', '23:00', '23:00']),
                'teams' => [
                    ['Рейнджерс', 'NYR', '#0038a8'], ['Бостон', 'BOS', '#d9a300'], ['Торонто', 'TOR', '#00205b'], ['Монреаль', 'MTL', '#af1e2d'],
                    ['Вашингтон', 'WSH', '#c8102e'], ['Тампа-Бэй', 'TBL', '#002868'], ['Флорида', 'FLA', '#041e42'], ['Эдмонтон', 'EDM', '#ff4c00'],
                    ['Колорадо', 'COL', '#6f263d'], ['Вегас', 'VGK', '#b4975a'], ['Даллас', 'DAL', '#006847'], ['Питтсбург', 'PIT', '#d9a000'],
                ],
            ],
            'nba' => [
                'sport' => 'basketball', 'name' => 'НБА', 'short' => 'НБА', 'country' => 'US',
                'weight' => 8, 'duration' => 140, 'round' => 'regular',
                'channels' => ['okko-sport', 'nba-tv', 'espn', 'tnt-us', 'setanta2'],
                'schedule' => array_fill(1, 7, ['00:30', '00:30', '03:00', '23:30']),
                'teams' => [
                    ['Лейкерс', 'LAL', '#552583'], ['Голден Стэйт', 'GSW', '#1d428a'], ['Бостон', 'BOS', '#007a33'], ['Денвер', 'DEN', '#0e2240'],
                    ['Милуоки', 'MIL', '#00471b'], ['Финикс', 'PHX', '#e56020'], ['Майами', 'MIA', '#98002e'], ['Нью-Йорк', 'NYK', '#f58426'],
                    ['Даллас', 'DAL', '#00538c'], ['Оклахома', 'OKC', '#007ac1'], ['Филадельфия', 'PHI', '#006bb6'], ['Миннесота', 'MIN', '#236192'],
                ],
            ],
            'euroleague' => [
                'sport' => 'basketball', 'name' => 'Евролига', 'short' => 'Евролига', 'country' => 'EU',
                'weight' => 6, 'duration' => 120, 'round' => 'regular',
                'channels' => ['setanta1', 'okko-sport', 'eurosport2'],
                'schedule' => [2 => ['17:00', '19:00', '19:00'], 4 => ['17:00', '19:00', '19:00'], 5 => ['17:00', '19:00']],
                'teams' => [
                    ['Реал Мадрид', 'RMB', '#d9a300'], ['Барселона', 'BAR', '#a50044'], ['Олимпиакос', 'OLY', '#e2231a'], ['Панатинаикос', 'PAO', '#007a3d'],
                    ['Фенербахче', 'FEN', '#002d72'], ['Монако', 'MON', '#e20a17'], ['Партизан', 'PAR', '#2b2b2b'], ['Црвена Звезда', 'CZV', '#d2232a'],
                    ['Маккаби', 'MTA', '#e5b800'], ['Анадолу Эфес', 'EFS', '#003478'],
                ],
            ],
            'vtb' => [
                'sport' => 'basketball', 'name' => 'Единая лига ВТБ', 'short' => 'ВТБ', 'country' => 'RU',
                'weight' => 5, 'duration' => 120, 'round' => 'regular',
                'channels' => ['match-arena', 'okko-sport'],
                'schedule' => [3 => ['16:00'], 6 => ['13:00', '15:30'], 7 => ['13:00', '15:30']],
                'teams' => [
                    ['ЦСКА', 'CSK', '#c8102e'], ['Зенит', 'ZEN', '#0090d0'], ['УНИКС', 'UNX', '#00843d'], ['Локомотив-Кубань', 'LKK', '#e30613'],
                    ['Парма', 'PRM', '#f28c28'], ['МБА', 'MBA', '#1f4e9c'], ['Енисей', 'ENS', '#00a3e0'], ['Автодор', 'AVT', '#e4002b'],
                ],
            ],
            'atp' => [
                'sport' => 'tennis', 'name' => 'ATP Masters 1000 · Шанхай', 'short' => 'ATP', 'country' => 'CN',
                'weight' => 6, 'duration' => 110, 'round' => 'tennis',
                'channels' => ['okko-sport', 'tennis-channel', 'eurosport1', 'bein2'],
                'schedule' => array_fill(1, 7, ['03:00', '05:00', '07:00', '09:00', '11:00', '13:00']),
                'teams' => [
                    ['Янник Синнер', 'SIN', '#3d9970'], ['Карлос Алькарас', 'ALC', '#e4572e'], ['Даниил Медведев', 'MED', '#2a4d9b'],
                    ['Александр Зверев', 'ZVE', '#4a4a4a'], ['Новак Джокович', 'DJO', '#0c4da2'], ['Андрей Рублёв', 'RUB', '#b22222'],
                    ['Тейлор Фриц', 'FRI', '#1f3a93'], ['Хольгер Руне', 'RUN', '#c8102e'], ['Карен Хачанов', 'KHA', '#4a4a9c'],
                    ['Джек Дрейпер', 'DRA', '#003399'], ['Алекс де Минор', 'DEM', '#00843d'], ['Бен Шелтон', 'SHE', '#1e90ff'],
                ],
            ],
            'wta' => [
                'sport' => 'tennis', 'name' => 'WTA 1000 · Ухань', 'short' => 'WTA', 'country' => 'CN',
                'weight' => 5, 'duration' => 100, 'round' => 'tennis',
                'channels' => ['okko-sport', 'tennis-channel', 'eurosport2'],
                'schedule' => array_fill(1, 7, ['04:00', '06:30', '09:00', '11:30']),
                'teams' => [
                    ['Арина Соболенко', 'SAB', '#e4002b'], ['Ига Свёнтек', 'SWI', '#c8102e'], ['Коко Гауфф', 'GAU', '#1e3a8a'],
                    ['Елена Рыбакина', 'RYB', '#00a3e0'], ['Мирра Андреева', 'AND', '#7c3aed'], ['Джессика Пегула', 'PEG', '#0f766e'],
                    ['Цинь Вэнь Чжэн', 'ZHE', '#dc2626'], ['Жасмин Паолини', 'PAO', '#2563eb'], ['Дарья Касаткина', 'KAS', '#9333ea'], ['Эмма Наварро', 'NAV', '#0369a1'],
                ],
            ],
            'f1' => [
                'sport' => 'motorsport', 'name' => 'Формула-1', 'short' => 'F1', 'country' => 'SG',
                'weight' => 9, 'duration' => 120, 'title' => 'Гран-при Сингапура', 'venue' => 'Марина-Бэй',
                'channels' => ['match-arena', 'sky-f1', 'canal-sport', 'espn2'],
                'sessions' => [
                    5 => [['09:30', 'Практика 1', 60], ['13:00', 'Практика 2', 60]],
                    6 => [['09:30', 'Практика 3', 60], ['13:00', 'Квалификация', 60]],
                    7 => [['12:00', 'Гонка', 120]],
                ],
            ],
            'ufc' => [
                'sport' => 'mma', 'name' => 'UFC', 'short' => 'UFC', 'country' => 'US',
                'weight' => 8, 'duration' => 180, 'venue' => 'UFC Apex, Лас-Вегас',
                'channels' => ['match-fighter', 'okko-sport', 'ufc-fight-pass', 'espn'],
                'sessions' => [
                    7 => [['00:00', 'Предварительный кард', 120], ['02:00', 'Основной кард', 180]],
                ],
                'teams' => [
                    ['Ислам Махачев', 'MAK', '#b91c1c'], ['Арман Царукян', 'TSA', '#1d4ed8'], ['Алекс Перейра', 'PER', '#15803d'],
                    ['Магомед Анкалаев', 'ANK', '#7c2d12'], ['Илия Топурия', 'TOP', '#b45309'], ['Шон О’Мэлли', 'OMA', '#7e22ce'],
                ],
            ],
        ];
    }
}
