<?php

use App\Guide\Competitions\CompetitionCatalog;
use App\Guide\Listings\TitleParser;
use App\Guide\TeamCategory;
use Carbon\CarbonImmutable;

// Названия — из настоящего телегида Dispatcharr.
it('parses match titles of different channels', function (string $title, array $expected, ?string $hint = null): void {
    $parsed = (new TitleParser)->parse($title, CarbonImmutable::parse('2026-10-06 12:00', 'UTC'), $hint);

    expect($parsed)->not->toBeNull();
    foreach ($expected as $field => $value) {
        expect($parsed->{$field})->toBe($value, "{$field} of «{$title}»");
    }
})->with([
    'setanta' => ['Футбол. АПЛ, 4 тур, Лидс – Ньюкасл', ['sport' => 'football', 'home' => 'Лидс', 'away' => 'Ньюкасл', 'competition' => 'АПЛ', 'round' => '4 тур', 'live' => false]],
    'match! live' => ['▶️ Гандбол. Чемп. России. Суперлига. Мужчины. ЦСКА - "СГАУ-Саратов".', ['sport' => 'handball', 'home' => 'ЦСКА', 'away' => 'СГАУ-Саратов', 'competition' => 'Чемпионат России · Суперлига · Мужчины', 'live' => true]],
    'quoted teams with cities' => ['Волейбол. Чемп. России. Суперлига. Женщины. "Динамо-Ак Барс" (Казань) - "Заречье-Одинцово" (Московская область) (6+).', ['sport' => 'volleyball', 'home' => 'Динамо-Ак Барс (Казань)', 'away' => 'Заречье-Одинцово (Московская область)']],
    'okko' => ['Цедевита-Олимпия (Словения) – Хапоэль И (Израиль). Баскетбол. Еврокубок', ['sport' => 'basketball', 'home' => 'Цедевита-Олимпия (Словения)', 'competition' => 'Еврокубок']],
    'okko abbreviation' => ['Штутгарт – Боруссия Д. Футбол. Чемпионат Германии', ['sport' => 'football', 'away' => 'Боруссия Д', 'competition' => 'Чемпионат Германии']],
    'khl' => ['⋗ КХЛ. Динамо (Минск) – СКА', ['sport' => 'hockey', 'home' => 'Динамо (Минск)', 'away' => 'СКА', 'competition' => 'КХЛ', 'live' => true]],
    'no space after dot' => ['КХЛ.Барыс – Авангард', ['sport' => 'hockey', 'home' => 'Барыс', 'competition' => 'КХЛ']],
    'youth league' => ['⋗ Чемпионат МХЛ. Тайфун -Красная Армия', ['sport' => 'hockey', 'home' => 'Тайфун', 'away' => 'Красная Армия', 'competition' => 'Чемпионат МХЛ']],
    'round as number' => ['▶️ Футбол. Лига Pari. Тур 13. "Нефтехимик" - "Челябинск".', ['home' => 'Нефтехимик', 'round' => 'Тур 13', 'competition' => 'Лига Pari']],
    'hungarian' => ['Labdarúgás: UEFA Nemzetek Ligája - Ukrajna - Magyarország mérkőzés.', ['sport' => 'football', 'home' => 'Ukrajna', 'away' => 'Magyarország', 'competition' => 'UEFA Nemzetek Ligája']],
    'unspaced hyphen' => ['Футбол. Лига 1, 4 тур, Ренн-Марсель', ['home' => 'Ренн', 'away' => 'Марсель', 'competition' => 'Лига 1']],
    'country as competition' => ['⋗ Хоккей. Беларусь. Динамо (Молодечно) – ХК Гомель. В перерыве Спорт-центр', ['sport' => 'hockey', 'country' => 'BY', 'competition' => 'Беларусь', 'away' => 'ХК Гомель']],
    'fighters' => ['Смешанные единоборства. UFC 332 в Солт-Лейк-Сити, США. Наталия Силва – Ван Цун', ['sport' => 'combat', 'home' => 'Наталия Силва', 'competition' => 'UFC 332 в Солт-Лейк-Сити']],
    'season stripped' => ['⋗ Модена – Верона. Итальянская Серия Б. Сезон 26/27', ['sport' => 'football', 'competition' => 'Итальянская Серия Б', 'live' => true]],
    'sport from channel' => ['⋗ Чжэцзян – Шанхай Порт. Китайская Суперлига. Сезон 2026', ['sport' => 'football', 'home' => 'Чжэцзян'], 'football'],
    'formula 1' => ['⋗ Автоспорт. Формула-1. Этап 17. Гран-при Сингапура. Квалификация', ['sport' => 'motorsport', 'home' => null, 'title' => 'Гран-при Сингапура', 'competition' => 'Формула-1', 'round' => 'Этап 17 · Квалификация', 'live' => true]],
    'race session is not a matchup' => ['Forma-1: Szingapúri nagydíj - Futam.', ['sport' => 'motorsport', 'home' => null, 'title' => 'Szingapúri nagydíj', 'round' => 'Futam']],
    'old match date' => ['Футзал. Суперлига. Норильск – Синара. 6 тур. Матч 1 (03.10.2026)', ['sport' => 'futsal', 'replay' => true, 'round' => '6 тур · Матч 1']],
    'part of a recording' => ['Греция – Германия. Футбол. Лига наций. 1–3 c', ['home' => 'Греция', 'replay' => true]],
    'sprint qualifying is a session' => ['⋗ Автоспорт. Формула-1. Этап 17. Гран-при Сингапура. Спринт-квалификация', ['title' => 'Гран-при Сингапура', 'round' => 'Этап 17 · Спринт-квалификация']],
    'formula 1 with a slash' => ['Формула 1: Гран-при Бахрейна / Гонка', ['sport' => 'motorsport', 'title' => 'Гран-при Бахрейна', 'competition' => 'Формула 1', 'round' => 'Гонка']],
    'tennis tournament as sport' => ['ATP 1000, Монреаль 1/32: Карен Хачанов – Теренс Атман', ['sport' => 'tennis', 'home' => 'Карен Хачанов', 'away' => 'Теренс Атман', 'competition' => 'ATP 1000']],
    'tennis slot without players' => ['Теннис. АТР. Шанхай', ['sport' => 'tennis', 'home' => null, 'competition' => 'АТР']],
    'biathlon race' => ['Биатлон. Кубок мира. Спринт. Мужчины', ['sport' => 'biathlon', 'home' => null, 'competition' => 'Кубок мира · Мужчины', 'round' => 'Спринт']],
    'biathlon relay' => ['Биатлон. Кубок мира. Смешанная эстафета', ['sport' => 'biathlon', 'title' => 'Смешанная эстафета', 'competition' => 'Кубок мира']],
]);

it('skips shows, studios and bare sports', function (string $title): void {
    expect((new TitleParser)->parse($title))->toBeNull();
})->with([
    'Футбол. АПЛ. Обзор тура',
    'Студия Окко.Спорт перед матчем Россия – Нигерия',
    'Ностальжи. Выпуск 8. Зенит – Рейнджерс. Сезон 2007/08',
    'Журнал Лиги чемпионов. Выпуск 4',
    'Теннис',
    'Век нашего спорта. 1975 - 1980 гг.',
    'Яркие матчи. КХЛ. Трактор – Амур',
    'Unbeaten',
]);

// Зарубежные телегиды: название, а соперники — в описании или подзаголовке
// (GuideImporter склеивает их через точку).
it('parses foreign guides', function (string $title, array $expected): void {
    $parsed = (new TitleParser)->parse($title, CarbonImmutable::parse('2026-10-06 12:00', 'UTC'));

    expect($parsed)->not->toBeNull();
    foreach ($expected as $field => $value) {
        expect($parsed->{$field})->toBe($value, "{$field} of «{$title}»");
    }
})->with([
    'czech, round glued to the home team' => ['Lední hokej. CHL, základní část Bílí Tygři Liberec - Frölunda.', ['sport' => 'hockey', 'home' => 'Bílí Tygři Liberec', 'away' => 'Frölunda']],
    'czech national teams' => ['Fotbal. Liga národů, Ligová fáze Chorvatsko - Španělsko.', ['sport' => 'football', 'home' => 'Chorvatsko', 'away' => 'Španělsko']],
    'hungarian description' => ['Kézilabda. Európa-liga, férfiak, csoportkör, premier, élő, HD, Mors-Thy Handbold - MOL Tatabánya KC', ['sport' => 'handball', 'home' => 'Mors-Thy Handbold', 'away' => 'MOL Tatabánya KC', 'live' => true]],
    'hungarian replay' => ['Kézilabda. Európa-liga, férfiak, csoportkör, ism., HD, Saint Raphael - Elverum', ['home' => 'Saint Raphael', 'replay' => true]],
    'romanian lines' => ['Handbal feminin. LIGA FLORILOR ET5. CS Rapid București-SCM Râmnicu Vâlcea. Transmisiune directa.', ['sport' => 'handball', 'home' => 'CS Rapid București', 'away' => 'SCM Râmnicu Vâlcea', 'live' => true]],
    'serbian' => ['Fudbal: Tsc - Grafičar', ['sport' => 'football', 'home' => 'Tsc', 'away' => 'Grafičar']],
    'american "at": home team second' => ['WNBA Basketball. Atlanta Dream at Washington Mystics', ['sport' => 'basketball', 'home' => 'Washington Mystics', 'away' => 'Atlanta Dream']],
    'league with sport' => ['NWSL Soccer. Angel City FC at Racing Louisville FC', ['sport' => 'football', 'home' => 'Racing Louisville FC']],
    'spanish with country codes' => ['Euroleague. Anadolu Efes (Tur) vs. Real Madrid (Esp), Fecha 2, Basketbol', ['sport' => 'basketball', 'home' => 'Anadolu Efes', 'away' => 'Real Madrid', 'round' => 'Fecha 2']],
    'german F1 session' => ['F1: Qualifying - GP Bahrain. &quot;GP Bahrain: Qualifying&quot;.', ['sport' => 'motorsport', 'home' => null, 'competition' => 'F1']],
    'english F1 session' => ['Formula 1. Singapore Grand Prix: Sprint Qualifying.', ['sport' => 'motorsport', 'title' => 'Singapore Grand Prix. Sprint Qualifying', 'competition' => 'Formula 1']],
    'lithuanian tennis' => ['Tenisas. ATP Challenger 125. Sen Tropezas. Edas Butvilas - Grigor Dimitrov', ['sport' => 'tennis', 'home' => 'Edas Butvilas', 'away' => 'Grigor Dimitrov']],
    'english UFC card' => ['UFC Fight Night: Allen vs. Duncan', ['sport' => 'combat', 'home' => 'Allen', 'away' => 'Duncan', 'competition' => 'UFC Fight Night']],
    'english biathlon' => ['Biathlon. World Cup. Men\'s Sprint', ['sport' => 'biathlon', 'home' => null, 'competition' => 'World Cup']],
    'spanish league name' => ['Fútbol Colombiano Primera División. Independiente Medellín vs. Independiente Santa Fe', ['sport' => 'football', 'home' => 'Independiente Medellín']],
    'sport from the league in the description' => ['Atlético La Paz vs. Venados. Jornada 11 del Torneo Apertura 2026 de la Liga Expansión MX', ['sport' => 'football', 'home' => 'Atlético La Paz', 'away' => 'Venados']],
    'english round after the teams' => ['Major Arena Soccer League. Baltimore v Milwaukee - SF G1', ['home' => 'Baltimore', 'away' => 'Milwaukee', 'round' => 'SF G1']],
    'french slash' => ['Bordeaux-Bègles / Lyon. Top 14 5e Journée', ['sport' => 'rugby', 'home' => 'Bordeaux-Bègles', 'away' => 'Lyon']],
    // Каталог знает американский футбол, бейсбол, крикет, дартс, гольф.
    'american college football' => ['College Football. Clemson vs. Miami', ['sport' => 'american-football', 'home' => 'Clemson', 'away' => 'Miami']],
    'hungarian MLB playoff' => ['MLB. Playoff, főcsoport-elődöntő, Atlanta Braves - Los Angeles Dodgers', ['sport' => 'baseball', 'home' => 'Atlanta Braves', 'away' => 'Los Angeles Dodgers']],
    // Sport 1 (Ukraine), 11.10.2026.
    'ukrainian hockey' => ['Хокей. Чемпіонат Чехії. Екстраліга. Плзень – Пардубіце', ['sport' => 'hockey', 'home' => 'Плзень', 'away' => 'Пардубіце']],
    // M4 Sport, 10.10.2026: «NB I.» — римский номер лиги, после него новый сегмент.
    'hungarian NB I' => ['Férfi kosárlabda NB I. Atomerőmű SE - Sopron KC.', ['sport' => 'basketball', 'home' => 'Atomerőmű SE', 'away' => 'Sopron KC']],
    'hungarian F1 race' => ['Forma 1 - Futam. Szingapúri Nagydíj.', ['sport' => 'motorsport', 'home' => null, 'competition' => 'Forma 1 - Futam']],
    // Setanta Sports 2 и M4 Sport: вида спорта нет ни в названии, ни у канала — соперников ищут везде.
    'no sport word anywhere' => ['⋗ Испания. «Бетис» – «Осасуна»', ['sport' => 'any', 'home' => 'Бетис', 'away' => 'Осасуна', 'live' => true]],
    // Sport 1 LT, 11.10.2026: литовские «futbolo», «krepšinio», «rankinio» — вид спорта.
    'lithuanian football' => ['Portugalijos Primeira futbolo lyga. FC Porto - SL Benfica', ['sport' => 'football', 'home' => 'FC Porto', 'away' => 'SL Benfica']],
    'lithuanian handball' => ['Rankinio čempionų lyga. SC Magdeburg - Industria Kielce', ['sport' => 'handball', 'home' => 'SC Magdeburg']],
    'league name without sport' => ['▶️ OTP Bank Liga. Kispest-Honvéd FC - Újpest FC.', ['sport' => 'any', 'home' => 'Kispest-Honvéd FC', 'away' => 'Újpest FC']],
]);

it('keeps the women category of foreign titles', function (): void {
    $parsed = (new TitleParser)->parse('Handbal feminin. LIGA FLORILOR ET5. CS Rapid București-SCM Râmnicu Vâlcea');

    expect(TeamCategory::of("{$parsed?->home} {$parsed?->away} {$parsed?->competition}"))->toBe('w');
});

it('skips foreign shows and sports we do not cover', function (string $title): void {
    expect((new TitleParser)->parse($title))->toBeNull();
})->with([
    // SportsGrid: разговор о матче во время игры, а не трансляция.
    'San Francisco 49ers vs. Seattle Seahawks NFL Football In-Game Live Gameday',
    // Canal+ Sport SK, 11.10.2026: «Zápasové studio» до и после матча.
    'Match Time. Liverpool - Manchester City',
    // Студии и обзоры (11.10.2026): NBA TV, V Sport, Sport en France, Sky Sports.
    'NBA Shot Clock: Games of the Night',
    'NHL On The Fly',
    'Le Grand Résumé. Marseille - Gap',
    "The Premier League's Greatest Games. 2015: Manchester City v Liverpool",
    'Lední hokej. Sestřih. Sparta - Kometa',
    'Labdarúgás. Bajnokok Ligája-magazin.',
    'UFC Epic Moments. Jones vs Gustafsson',
    'Live: NHL Tonight',
    'F1 Drivers&apos; Press Conference',
    'F1 Paddock Live Pit Walk',
]);

it('does not take F1 H2O powerboats for Formula 1', function (): void {
    $parsed = (new TitleParser)->parse('F1 H2O: Grand Prix Chin');

    expect(CompetitionCatalog::match((string) $parsed?->sport, $parsed?->competition))->toBeNull();
});

it('keeps full match replays as match programmes', function (): void {
    $parsed = (new TitleParser)->parse('Football. Arsenal vs Chelsea. Full Match Replay');
    expect($parsed?->home)->toBe('Arsenal')->and($parsed?->away)->toBe('Chelsea')->and($parsed?->replay)->toBeTrue();
});

it('leaves an archived national-team replay without a sport ambiguous for the matcher', function (): void {
    $parsed = (new TitleParser)->parse('Germany v Spain - Semi-finals - 2010 FIFA World Cup South Africa™ - Full Match Replay');
    expect($parsed?->replay)->toBeTrue()->and($parsed?->knowsSport())->toBeFalse();
});

it('skips archives of old seasons but keeps the current one and club names with years', function (): void {
    $airsAt = CarbonImmutable::parse('2026-10-11 10:20', 'UTC');
    $parser = new TitleParser;

    // Dynamo Kyiv TV и Arena Premium 1, 11.10.2026: архив, а не трансляция.
    expect($parser->parse('Футбол. Чемпіонат України. Сезон 2019/20. Динамо Київ - Олександрія', $airsAt))->toBeNull()
        ->and($parser->parse('Fudbal - Klasici Premijer lige: Liverpool - Manchester City, 2017/18', $airsAt))->toBeNull()
        ->and($parser->parse('Футбол. Чемпіонат України. Сезон 2026/27. Динамо Київ - Карпати', $airsAt)?->home)->toBe('Динамо Київ')
        ->and($parser->parse('⋗ Модена – Верона. Итальянская Серия Б. Сезон 26/27', $airsAt)?->away)->toBe('Верона')
        ->and($parser->parse('⋗ Футбол. Латвія. Вірсліга. Тукумс 2000 – Рига', $airsAt)?->home)->toBe('Тукумс 2000')
        // Sky Sports Football: финал 2009 года — архив.
        ->and($parser->parse('EFL Cup. 2009 Final: Manchester United v Tottenham Hotspur', $airsAt))->toBeNull();
});
