<?php

namespace App\Sports\Flashscore;

use App\Guide\Teams\NationalTeams;
use App\Sports\Catalog\CatalogWriter;
use App\Sports\Catalog\Names;
use App\Sports\Catalog\SourceIds;
use App\Sports\CatalogDatabase;
use App\Sports\Models\Athlete;
use App\Sports\Models\CatalogModel;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Country;
use App\Sports\Models\Discipline;
use App\Sports\Models\Edition;
use App\Sports\Models\Entry;
use App\Sports\Models\Event;
use App\Sports\Models\Provider;
use App\Sports\Models\Sport;
use App\Sports\Models\Stage;
use App\Sports\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Разобранная страница Flashscore → каталог. Сущности находятся по
 * идентификаторам источника в своих пространствах имён (template —
 * турнир, tournament — сезон, stage — стадия, stage-event — сессия/этап/
 * гонка, participant — команда или спортсмен, event — матч, country —
 * страна). Имя — только подпись.
 *
 * Каталог — расписание, а не результаты: счёт, сеты и протоколы не читаются,
 * прошедшее не заводится. Поля, проверенные на образцах
 * (docs/flashscore-catalog-coverage.md): ZL — адрес раздела и турнира, ZB/ZY
 * — раздел заголовка, ZEE/ZE/ZC — турнир/сезон/стадия, ZAE/ZN/ZSS — сессия:
 * название, «начало|конец|место|дистанция», статус. AD — начало, AB — статус
 * (1/2/3), PX/PY — участники (пара — «id/id»), CA/GA, CB/GB — страны
 * спортсменов, NA — команда гонщика. Код AC хранится как есть.
 */
final class PageImporter
{
    private const string LOGO_URL = 'https://static.flashscore.com/res/image/data/';

    /** Регионы в адресах Flashscore — разделы навигации, не страны. */
    public const array REGIONS = [
        'europe' => ['Europe', 'Европа'], 'world' => ['World', 'Мир'], 'asia' => ['Asia', 'Азия'], 'africa' => ['Africa', 'Африка'],
        'south-america' => ['South America', 'Южная Америка'], 'north-central-america' => ['North & Central America', 'Северная и Центральная Америка'],
        'australia-oceania' => ['Australia & Oceania', 'Австралия и Океания'], 'oceania' => ['Oceania', 'Океания'],
    ];

    /** Семейства, где участник — спортсмен (или пара), а не команда. */
    private const array ATHLETE_FAMILIES = ['racket', 'duel', 'bout', 'race', 'golf'];

    /** @var list<string> */
    public array $skipped = [];

    /** @var array<int, array{0: string, 1: string}> турнир → [первая дата, последняя дата] */
    public array $windows = [];

    public int $events = 0;

    /** @var array<string, string> id шаблона → общее название блоков турнира на странице */
    private array $bases = [];

    /** @var array<string, array<string, array<string, string>>> заголовки английской страницы: турнир → раздел → поля */
    private array $englishHeaders = [];

    /**
     * @param  list<string>  $nationalPaths  адреса турниров сборных (ZL)
     */
    public function __construct(
        private readonly CatalogWriter $writer,
        private readonly Provider $provider,
        private readonly array $nationalPaths = [],
    ) {}

    /**
     * Страница на основном языке: сущности, события, счёт.
     *
     * @param  array{id: string, national?: bool}|null  $subject  команда страницы команды
     */
    public function import(ParsedPage $page, Sport $sport, string $url, ?array $subject = null): void
    {
        $this->rememberBases($page);
        $raceBlocks = $this->raceBlocksPerTemplate($page, $sport);

        foreach ($page->blocks as $block) {
            $header = $block['header'];
            $this->englishHeaders[$header['ZEE'] ?? ''][$header['ZB'] ?? ''] = $header;
            $context = $this->context($page, $sport, $header, $url);
            if ($context === null) {
                continue;
            }

            if (in_array($sport->family, ['race', 'golf'], true)) {
                $this->raceBlock($context, $header, $block['events'], ($raceBlocks[$header['ZEE'] ?? ''] ?? 0) > 1);

                continue;
            }
            foreach ($block['events'] as $fields) {
                $this->event($context, $fields, $subject);
            }
        }
    }

    /**
     * Страница на другом языке: только названия уже известных сущностей —
     * по идентификаторам, новых сущностей отсюда не появляется.
     */
    public function names(ParsedPage $page, Sport $sport, string $locale, string $url): int
    {
        $this->rememberBases($page);
        $count = 0;
        $name = function (string $entity, string $scope, ?string $id, ?string $value) use ($locale, $url, &$count) {
            if ($id === null || $value === null || trim($value) === '') {
                return;
            }
            $found = SourceIds::find($entity, $this->provider->id, $scope, $id);
            $model = $found !== null ? $this->model($entity, $found) : null;
            if ($model !== null) {
                $this->writer->name($model, $locale, $value, provider: $this->provider, url: $url);
                $count++;
            }
        };

        foreach ($page->blocks as $block) {
            $header = $block['header'];
            [$competitionName] = $this->competitionName($header);
            $name('competition', 'template', $header['ZEE'] ?? null, $competitionName);
            $categoryKey = array_values(array_filter(explode('/', (string) ($header['ZL'] ?? ''))))[1] ?? '';
            $english = $this->englishHeaders[$header['ZEE'] ?? ''][$header['ZB'] ?? ''] ?? null;
            $sameCategory = $english !== null && $this->headerMatches($english, $categoryKey);
            $category = $this->categoryByPath($sport, (string) ($header['ZL'] ?? ''));
            $geo = $category !== null && in_array($category->kind, ['country', 'region'], true);
            // Раздел — по адресу (он одинаков на сайтах на всех языках); страна/регион — если заголовок о нём.
            if ($category !== null && (! $geo || $sameCategory)) {
                $local = $geo ? ($header['ZY'] ?? null) : ($header['ZAF'] ?? null);
                if ($local !== null && trim($local) !== '') {
                    $this->writer->name($category, $locale, $local, provider: $this->provider, url: $url);
                    $count++;
                }
            }
            if ($sameCategory && $this->categoryKind($sport, $header) === 'country') {
                $name('country', 'country', $header['ZB'] ?? null, $header['ZY'] ?? null);
            }

            // Сессия или этап — по id стадии; матч — по id события.
            $session = isset($header['ZC']) ? SourceIds::find('event', $this->provider->id, 'stage-event', $header['ZC']) : null;
            if ($session !== null) {
                Event::query()->whereKey($session)->update(['name_local' => $header['ZAE'] ?? $competitionName]);
            }

            foreach ($block['events'] as $fields) {
                $eventId = SourceIds::find('event', $this->provider->id, 'event', $fields['AA']);
                if ($eventId !== null && isset($fields['ER']) && $fields['ER'] !== '') {
                    Event::query()->whereKey($eventId)->update(['round_label_local' => $fields['ER']]);
                }
                foreach ([['PX', 'AE', ['FH', 'FJ'], ['CA', 'GA'], ['FU', 'FW']], ['PY', 'AF', ['FK', 'FL'], ['CB', 'GB'], ['FV', 'FX']]] as [$idKey, $nameKey, $memberNames, $countryIds, $countryNames]) {
                    $ids = $this->ids($fields[$idKey] ?? null);
                    if (! in_array($sport->family, self::ATHLETE_FAMILIES, true)) {
                        $name('team', 'participant', $ids[0] ?? null, $fields[$nameKey] ?? null);

                        continue;
                    }
                    foreach ($ids as $index => $id) {
                        $name('athlete', 'participant', $id, count($ids) > 1 ? ($fields[$memberNames[$index]] ?? null) : ($fields[$nameKey] ?? null));
                        $name('country', 'country', $fields[$countryIds[$index]] ?? null, $fields[$countryNames[$index]] ?? null);
                    }
                }
            }
        }

        // Этап целиком (Гран-при, тур) — русское название турнира.
        foreach (Event::query()->whereNull('parent_id')->whereIn('kind', ['meeting', 'tournament'])->whereHas('children')->get() as $parent) {
            $local = $parent->competition->names()->where('locale', $locale)->where('kind', 'official')->value('name');
            if ($local !== null) {
                $parent->update(['name_local' => $local]);
            }
        }

        return $count;
    }

    /**
     * Раздел, турнир, сезон и стадия блока.
     *
     * @param  array<string, string>  $header
     * @return array{sport: Sport, category: Category, competition: Competition, edition: Edition|null, stage: Stage|null, national: bool, discipline: Discipline|null}|null
     */
    private function context(ParsedPage $page, Sport $sport, array $header, string $url): ?array
    {
        $path = $header['ZL'] ?? null;
        $segments = $path !== null ? array_values(array_filter(explode('/', $path))) : [];
        $template = $header['ZEE'] ?? null;
        $slugs = (array) config("sports.catalog.sports.{$sport->key}.flashscore");
        $groups = is_string(array_key_first($slugs)) ? $slugs : array_fill_keys($slugs, null);
        if (count($segments) !== 3 || $template === null || ! array_key_exists($segments[0], $groups)) {
            $this->skipped[] = 'block without a competition path of this sport: '.($header['ZA'] ?? '?');

            return null;
        }
        [$source, $categoryKey, $slug] = $segments;

        // Несколько разделов Flashscore на один вид спорта (биатлон, горные лыжи…): ключ — с разделом.
        $group = $groups[$source] !== null
            ? $this->writer->category($sport, $sport->key === 'winter-sports' ? 'discipline' : 'series', $source, (string) $groups[$source], position: array_search($source, array_keys($groups), true) + 1)
            : null;
        $category = $this->category($sport, $header, $group !== null ? "{$source}-{$categoryKey}" : $categoryKey, $group);
        $discipline = $sport->key === 'esports' ? $this->writer->discipline($sport, $categoryKey, (string) ($header['ZAF'] ?? Str::headline($categoryKey))) : null;

        [$competitionName, $stageName] = $this->competitionName($header);
        $national = in_array($path, $this->nationalPaths, true);
        $existing = SourceIds::find('competition', $this->provider->id, 'template', $template);
        // Турнир, заведённый по дневному списку (по адресу), становится этим турниром, а не копией.
        $provisional = $existing === null ? SourceIds::find('competition', $this->provider->id, 'path', $path) : null;
        if ($provisional !== null) {
            SourceIds::link('competition', $this->provider->id, 'template', $template, $provisional);
            $existing = $provisional;
        }
        $competition = $this->writer->competition($sport, $category, array_filter([
            // «NHL - Pre-season» со страницы, где у турнира один блок, не заменяет известное «NHL».
            'name' => $existing !== null && $stageName === null && str_starts_with($competitionName, Competition::query()->whereKey($existing)->value('name').' - ') ? null : $competitionName,
            'slug' => $existing === null ? $slug : null,
            'logo_url' => isset($header['OAJ']) && $header['OAJ'] !== '' ? self::LOGO_URL.$header['OAJ'] : null,
            'kind' => $existing === null ? 'unknown' : null,
            'scope' => $category->kind === 'country' ? 'domestic' : ($category->kind === 'region' ? 'international' : null),
            'participant_kind' => match (true) {
                $national => 'national',
                in_array($sport->family, self::ATHLETE_FAMILIES, true) => 'individual',
                $category->kind === 'country' => 'club',
                default => null,
            },
            'entry_format' => match (true) {
                $sport->family !== 'racket' => in_array($sport->family, ['team', 'cricket'], true) ? null : 'individual',
                str_contains($categoryKey, 'mixed') => 'mixed_doubles',
                str_contains($categoryKey, 'doubles') => 'doubles',
                default => 'singles',
            },
            'gender' => $this->gender($categoryKey, $competitionName),
            'age_group' => preg_match('~\bU(\d{2})\b~', $competitionName, $age) ? 'u'.$age[1] : null,
            'country_id' => $category->kind === 'country' ? $category->country_id : null,
            'region_id' => $category->region_id,
            'discipline_id' => $discipline?->id,
        ], fn ($value) => $value !== null), [$this->provider, 'template', $template, rtrim(config('sports.catalog.flashscore.hosts.en'), '/').$path]);
        $this->writer->name($competition, 'en', $competition->name, provider: $this->provider, url: $url);

        $edition = $this->edition($page, $competition, $header);
        $stage = null;
        $stageId = $header['ZC'] ?? null;
        // Сессии гонок — сами события, а не стадии.
        if (! in_array($sport->family, ['race', 'golf'], true)) {
            $stageName ??= $header['ZAE'] ?? null;
            if ($edition !== null && $stageId !== null && $stageName !== null) {
                $stage = $this->writer->stage($edition, $this->stageKind($stageName), $stageName, source: [$this->provider, 'stage', $stageId]);
            }
        }

        return ['sport' => $sport, 'category' => $category, 'competition' => $competition, 'edition' => $edition, 'stage' => $stage, 'national' => $national, 'discipline' => $discipline];
    }

    /**
     * Название турнира и стадии: «UEFA Nations League - League A» при ZK =
     * «UEFA Nations League» — турнир и стадия «League A».
     *
     * @param  array<string, string>  $header
     * @return array{0: string, 1: string|null}
     */
    private function competitionName(array $header): array
    {
        $name = $this->title($header);
        $parent = $header['ZK'] ?? null;
        if ($parent !== null && str_starts_with($name, $parent.' - ')) {
            return [$parent, substr($name, strlen($parent) + 3)];
        }

        $base = $this->bases[$header['ZEE'] ?? ''] ?? null;
        $stage = $base !== null && $base !== $name ? $this->stageOf($name, $base) : null;
        if ($stage !== null) {
            return [$base, $stage];
        }

        return [$parent ?? $name, null];
    }

    /**
     * Название из заголовка блока без раздела: «USA: NHL - Pre-season» → «NHL - Pre-season».
     * У сессий гонок название сессии (ZAE) — не часть названия этапа.
     *
     * @param  array<string, string>  $header
     */
    private function title(array $header): string
    {
        $title = (string) ($header['ZA'] ?? '');
        $title = str_contains($title, ': ') ? substr($title, strpos($title, ': ') + 2) : $title;
        // До двоеточия обычно раздел («ENGLAND», «FORMULA 1»). У зимних гонок там место —
        // его даёт поле QMN («Oestersund»), и оно остаётся в названии.
        if (isset($header['QMN']) && trim($header['QMN']) !== '') {
            $title = trim($header['QMN']).': '.$title;
        }
        $session = $header['ZAE'] ?? null;
        if ($session !== null && str_ends_with($title, ' - '.$session)) {
            $title = substr($title, 0, -strlen(' - '.$session));
        }

        return $title;
    }

    /**
     * Блоки одного турнира (один id шаблона) на странице: «NHL» и «NHL -
     * Pre-season», «Shanghai (China), hard» и «Shanghai (China) - Qualification,
     * hard». Общая часть — название турнира, остальное — стадия.
     */
    private function rememberBases(ParsedPage $page): void
    {
        $groups = [];
        foreach ($page->blocks as $block) {
            $groups[$block['header']['ZEE'] ?? ''][] = $this->title($block['header']);
        }

        $this->bases = [];
        foreach ($groups as $template => $names) {
            $names = array_values(array_unique($names));
            // Заголовок страницы («Champions League»), если все блоки — его стадии.
            if ($page->name !== null && array_reduce($names, fn (bool $carry, string $name) => $carry && $this->stageOf($name, $page->name) !== null, true)) {
                $this->bases[(string) $template] = $page->name;

                continue;
            }
            if (count($names) < 2) {
                continue;
            }
            usort($names, fn (string $a, string $b) => mb_strlen($a) <=> mb_strlen($b));
            $base = $names[0];
            $all = array_reduce(array_slice($names, 1), fn (bool $carry, string $name) => $carry && $this->stageOf($name, $base) !== null, true);
            if ($all) {
                $this->bases[(string) $template] = $base;
            }
        }
    }

    private function stageOf(string $name, string $base): ?string
    {
        if (str_starts_with($name, $base.' - ')) {
            return substr($name, strlen($base) + 3);
        }

        return preg_match('~^(.*) - ([^,]+)(,.*)?$~u', $name, $match) && $base === $match[1].($match[3] ?? '') ? $match[2] : null;
    }

    /** Раздел каталога по адресу турнира (/sport/category/slug/), с учётом групп разделов. */
    private function categoryByPath(Sport $sport, string $path): ?Category
    {
        $segments = array_values(array_filter(explode('/', $path)));
        if (count($segments) !== 3) {
            return null;
        }
        $grouped = is_string(array_key_first((array) config("sports.catalog.sports.{$sport->key}.flashscore")));
        $key = $grouped ? "{$segments[0]}-{$segments[1]}" : $segments[1];

        return Category::query()->where('sport_id', $sport->id)->where('key', $key)->first();
    }

    /**
     * Сколько блоков-сессий у каждого турнира на странице: больше одного — этап с сессиями.
     *
     * @return array<string, int>
     */
    private function raceBlocksPerTemplate(ParsedPage $page, Sport $sport): array
    {
        if ($sport->family !== 'race') {
            return [];
        }

        $counts = [];
        foreach ($page->blocks as $block) {
            $template = $block['header']['ZEE'] ?? '';
            $counts[$template] = ($counts[$template] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<string, string>  $header
     */
    private function categoryKind(Sport $sport, array $header): string
    {
        return self::kindFor($sport, array_values(array_filter(explode('/', (string) ($header['ZL'] ?? ''))))[1] ?? '');
    }

    /** Вид раздела по виду спорта и ключу из адреса: страна, регион, тур, серия, весовая категория, игра. */
    public static function kindFor(Sport $sport, string $key): string
    {
        return match ($sport->family) {
            'team', 'cricket', 'duel' => match (true) {
                $sport->key === 'esports' => 'game',
                isset(self::REGIONS[$key]) => 'region',
                default => 'country',
            },
            'racket', 'golf' => 'tour',
            'race' => $sport->key === 'winter-sports' ? 'other' : 'series',
            'bout' => 'weight_class',
            default => 'other',
        };
    }

    /**
     * @param  array<string, string>  $header
     */
    private function category(Sport $sport, array $header, string $key, ?Category $group = null): Category
    {
        $kind = $this->categoryKind($sport, $header);
        $plainKey = array_values(array_filter(explode('/', (string) ($header['ZL'] ?? ''))))[1] ?? $key;
        $matches = $this->headerMatches($header, $plainKey);
        $existing = Category::query()->where('sport_id', $sport->id)->where('key', $key)->first();
        // Заголовок блока описывает другой раздел, чем адрес турнира: раздел уже известен — его и берём.
        if (! $matches && $existing !== null) {
            return $existing;
        }

        // У гонок ZY — страна этапа, а раздел — серия или дисциплина: название из ZAF.
        $label = in_array($kind, ['country', 'region'], true) ? ($header['ZY'] ?? $header['ZAF'] ?? null) : ($header['ZAF'] ?? $header['ZY'] ?? null);
        $name = $matches || ! in_array($kind, ['country', 'region'], true) ? (string) ($label ?? Str::headline($plainKey)) : (self::REGIONS[$plainKey][0] ?? Str::headline($plainKey));
        $country = null;
        $region = null;
        if ($kind === 'region') {
            $region = $this->writer->region($plainKey, self::REGIONS[$plainKey][0], self::REGIONS[$plainKey][1]);
        } elseif ($kind === 'country') {
            $country = $matches ? $this->country($header['ZB'] ?? null, $name, $plainKey) : Country::query()->where('code', $plainKey)->first();
            $kind = $country !== null ? 'country' : 'other';
        }

        // Теннис: ATP и WTA — группы навигации над разрядами (atp-singles, atp-doubles…).
        $parent = $group;
        if ($sport->key === 'tennis' && preg_match('~^(atp|wta)-~', $key, $tour)) {
            $parent = $this->writer->category($sport, 'tour', $tour[1], strtoupper($tour[1]), position: $tour[1] === 'atp' ? 1 : 2);
        }
        $category = $this->writer->category($sport, $kind, $key, $name, $country, $region, $parent);
        if ($matches && isset($header['ZB']) && in_array($kind, ['country', 'region'], true)) {
            SourceIds::link('category', $this->provider->id, "category:{$sport->key}", $header['ZB'], $category->id);
        }
        $this->writer->name($category, 'en', $name, provider: $this->provider);

        return $category;
    }

    /**
     * ZB/ZY — раздел заголовка блока; обычно это раздел из адреса турнира, но не
     * всегда: отбор ЧМ среди женщин — «EUROPE:», а адрес — /football/world/….
     * Совпадают, если название раздела даёт тот же адресный ключ.
     *
     * @param  array<string, string>  $header
     */
    private function headerMatches(array $header, string $key): bool
    {
        $label = $header['ZY'] ?? $header['ZAF'] ?? null;

        return $label !== null && (Names::slug($label) === $key || Names::slug((string) ($header['ZAF'] ?? '')) === $key);
    }

    /**
     * Страна по id Flashscore (один набор id у разделов-стран и у
     * гражданства спортсменов). ISO-код — только если ICU узнаёт название;
     * Англия, Шотландия… — спортивные территории без ISO.
     */
    private function country(?string $id, ?string $name, ?string $code = null): ?Country
    {
        if ($id === null || $name === null || $id === '0' || trim($name) === '') {
            return null;
        }

        $existing = SourceIds::find('country', $this->provider->id, 'country', $id);
        if ($existing !== null) {
            return Country::query()->find($existing);
        }

        // Страна, заведённая по дневному списку (без id источника), получает id, а не копию.
        $code ??= Names::slug($name);
        $adopted = Country::query()->where('code', $code)->whereNotIn('id', CatalogDatabase::connection()->table('catalog_country_sources')->select('country_id'))->first();
        if ($adopted !== null) {
            SourceIds::link('country', $this->provider->id, 'country', $id, $adopted->id);

            return $adopted;
        }

        $iso = NationalTeams::parse($name)['code'] ?? null;
        if (Country::query()->where('code', $code)->exists()) {
            $code .= '-'.$id;
        }
        $country = $this->writer->country($code, $name, [
            'kind' => $iso !== null && str_starts_with($iso, 'gb-') ? 'sporting_territory' : 'country',
            'iso2' => $iso !== null && strlen($iso) === 2 ? strtoupper($iso) : null,
            'flag_url' => $iso !== null ? "https://flagcdn.com/w80/{$iso}.png" : null,
        ], [$this->provider, 'country', $id]);
        $this->writer->name($country, 'en', $name, provider: $this->provider);

        return $country;
    }

    /**
     * Сезон: по id источника (ZE); новый — только с подписью со страницы этого
     * же сезона («2026/2027»). У этапов гонок id сезона нет (ZE = 0) — сезон
     * по подписи страницы, если блок — турнир этой страницы. Подписи нет —
     * событие без сезона, год не угадывается.
     *
     * @param  array<string, string>  $header
     */
    private function edition(ParsedPage $page, Competition $competition, array $header): ?Edition
    {
        $id = $header['ZE'] ?? null;
        if ($id === null || $id === '' || $id === '0') {
            $ownTemplate = $page->templateId ?? $this->singleTemplate($page);
            if ($page->editionLabel === null || $ownTemplate === null || $ownTemplate !== ($header['ZEE'] ?? null)) {
                return null;
            }

            return $this->writer->edition($competition, str_replace('/', '-', $page->editionLabel), array_filter([
                'label' => $page->editionLabel,
                'is_current' => ! $page->archive ? true : null,
            ], fn ($value) => $value !== null));
        }

        $known = SourceIds::find('edition', $this->provider->id, 'tournament', $id);
        $own = $page->tournamentId === $id && $page->editionLabel !== null;
        if ($known === null && ! $own) {
            return null;
        }

        $edition = $known !== null ? Edition::query()->find($known) : null;
        $label = $own ? $page->editionLabel : $edition?->label;

        return $this->writer->edition($competition, str_replace('/', '-', (string) $label), array_filter([
            'label' => $label,
            'is_current' => $own && ! $page->archive ? true : null,
        ], fn ($value) => $value !== null), [$this->provider, 'tournament', $id]);
    }

    private function singleTemplate(ParsedPage $page): ?string
    {
        $templates = array_values(array_unique(array_filter(array_map(fn (array $block) => $block['header']['ZEE'] ?? null, $page->blocks))));

        return count($templates) === 1 ? $templates[0] : null;
    }

    private function stageKind(string $name): string
    {
        $lower = mb_strtolower($name);

        return match (true) {
            str_contains($lower, 'qualif') => 'qualification',
            str_contains($lower, 'play off') || str_contains($lower, 'playoff') || str_contains($lower, 'play-off') => 'playoff',
            str_contains($lower, 'group') => 'group',
            str_contains($lower, 'league') => 'league',
            default => 'other',
        };
    }

    /**
     * Очная встреча: матч, бой, партия.
     *
     * @param  array{sport: Sport, category: Category, competition: Competition, edition: Edition|null, stage: Stage|null, national: bool, discipline: Discipline|null}  $context
     * @param  array<string, string>  $fields
     * @param  array{id: string, national?: bool}|null  $subject  команда страницы команды
     */
    private function event(array $context, array $fields, ?array $subject): void
    {
        $sport = $context['sport'];
        $start = isset($fields['AD']) && ctype_digit($fields['AD']) ? CarbonImmutable::createFromTimestampUTC((int) $fields['AD']) : null;
        if ($start === null) {
            $this->skipped[] = "event {$fields['AA']}: no start time";

            return;
        }

        // Расписание, а не результаты: прошедший матч, которого ещё нет, не заводим;
        // известный получает статус «завершён» и пропадает из расписания.
        if (($fields['AB'] ?? null) === '3' && SourceIds::find('event', $this->provider->id, 'event', $fields['AA']) === null) {
            return;
        }

        $sides = [];
        foreach ([1 => ['PX', 'AE', 'WM', 'OA', ['FH', 'FJ'], ['CA', 'GA'], ['FU', 'FW']], 2 => ['PY', 'AF', 'WN', 'OB', ['FK', 'FL'], ['CB', 'GB'], ['FV', 'FX']]] as $side => [$idKey, $nameKey, $codeKey, $logoKey, $memberNames, $countryIds, $countryNames]) {
            $ids = $this->ids($fields[$idKey] ?? null);
            $entry = match (true) {
                $ids === [] => null,
                in_array($sport->family, self::ATHLETE_FAMILIES, true) => $this->athleteEntry($sport, $ids, $fields, $nameKey, $memberNames, $countryIds, $countryNames),
                default => $this->teamEntry($context, $ids[0], $fields[$nameKey] ?? null, $fields[$codeKey] ?? null, $fields[$logoKey] ?? null, $subject),
            };
            $sides[] = ['side' => $side, 'entry' => $entry, 'label' => $entry === null ? 'Уточняется' : null];
        }

        $status = match ($fields['AB'] ?? null) {
            '1' => 'scheduled',
            '2' => 'live',
            '3' => 'finished',
            default => 'unknown',
        };

        $event = $this->writer->event($context['competition'], [
            'edition_id' => $context['edition']?->id,
            'stage_id' => $context['stage']?->id,
            'kind' => $sport->family === 'bout' ? 'bout' : 'match',
            'round_label' => $fields['ER'] ?? null,
            'date' => $start->setTimezone(config('sports.timezone'))->toDateString(),
            'starts_at' => $start,
            'time_precision' => 'exact',
            'source_timezone' => 'UTC',
            'status' => $status,
            'raw_status' => ($fields['AB'] ?? '?').':'.($fields['AC'] ?? '?'),
        ], [$this->provider, 'event', $fields['AA'], null]);
        $this->writer->eventEntries($event, $sides);

        foreach ($sides as $side) {
            if ($side['entry'] !== null && $context['edition'] !== null) {
                $this->writer->editionEntry($context['edition'], $side['entry']);
            }
        }

        $this->count($context['competition'], $event, $start);
    }

    /**
     * Сессия, этап, гонка или турнир по гольфу: один блок — одно событие с
     * участниками (без протокола). Несколько блоков одного турнира — сессии
     * этапа (Гран-при) или этапы многодневки под общим родителем. Прошедший
     * блок, которого ещё нет, не заводится.
     *
     * @param  array{sport: Sport, category: Category, competition: Competition, edition: Edition|null, stage: Stage|null, national: bool, discipline: Discipline|null}  $context
     * @param  array<string, string>  $header
     * @param  list<array<string, string>>  $rows
     */
    private function raceBlock(array $context, array $header, array $rows, bool $withParent): void
    {
        $sport = $context['sport'];
        $stageId = $header['ZC'] ?? null;
        if ($stageId === null || $rows === []) {
            $this->skipped[] = 'race block without stage id or entrants: '.($header['ZA'] ?? '?');

            return;
        }

        $info = explode('|', (string) ($header['ZN'] ?? ''));
        $epochs = array_filter(array_map(fn (array $row) => isset($row['AD']) && ctype_digit($row['AD']) ? (int) $row['AD'] : null, $rows));
        $start = ctype_digit($info[0]) ? (int) $info[0] : ($epochs !== [] ? min($epochs) : null);
        if ($start === null) {
            $this->skipped[] = 'race block without start: '.($header['ZA'] ?? '?');

            return;
        }
        $startsAt = CarbonImmutable::createFromTimestampUTC($start);
        $endsAt = isset($info[1]) && ctype_digit($info[1]) ? CarbonImmutable::createFromTimestampUTC((int) $info[1]) : null;
        $golf = $sport->family === 'golf';
        $venue = $golf ? null : (implode(' · ', array_filter([trim($info[2] ?? ''), trim($info[3] ?? '')])) ?: null);

        // Статусы строк как строки: «1», «2», «3» (без превращения в числовые ключи).
        $statuses = array_values(array_unique(array_map(fn (array $row) => (string) ($row['AB'] ?? ''), $rows)));
        $status = match ($header['ZSS'] ?? null) {
            '1' => 'scheduled',
            '2' => 'live',
            '3' => 'finished',
            null => match (true) {
                in_array('2', $statuses, true) => 'live',
                $statuses === ['3'] => 'finished',
                $statuses === ['1'] => 'scheduled',
                default => 'unknown',
            },
            default => 'unknown',
        };
        if ($status === 'finished' && SourceIds::find('event', $this->provider->id, 'stage-event', $stageId) === null) {
            return;
        }

        $parent = $withParent ? $this->parentEvent($context, $startsAt) : null;
        $event = $this->writer->event($context['competition'], array_filter([
            'edition_id' => $context['edition']?->id,
            'parent_id' => $parent?->id,
            'kind' => $golf ? 'tournament' : ($parent !== null ? 'session' : 'race'),
            'name' => $header['ZAE'] ?? $context['competition']->name,
            'date' => $startsAt->setTimezone(config('sports.timezone'))->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'time_precision' => 'exact',
            'source_timezone' => 'UTC',
            'status' => $status,
            'raw_status' => ($header['ZSS'] ?? '?').':'.implode(',', $statuses),
            'venue' => $venue,
        ], fn ($value) => $value !== null), [$this->provider, 'stage-event', $stageId, null]);

        $entries = [];
        foreach ($rows as $index => $row) {
            $ids = $this->ids($row['PX'] ?? null);
            if (count($ids) !== 1) {
                continue;
            }
            $entry = $this->athleteEntry($sport, $ids, $row, 'AE', ['FH', 'FJ'], ['CA', 'GA'], ['FU', 'FW']);
            if ($entry === null) {
                continue;
            }
            $team = trim((string) ($row['NA'] ?? '')) ?: null;
            $entries[] = ['entry' => $entry, 'affiliation' => $team, 'position' => $index];
            if ($context['edition'] !== null) {
                $this->writer->editionEntry($context['edition'], $entry, $team);
            }
        }
        $this->writer->eventEntries($event, $entries);

        $this->count($context['competition'], $event, $startsAt);
        if ($parent !== null) {
            $this->refreshParent($parent);
        }
    }

    /**
     * Этап (Гран-при) или многодневка (тур) — родитель сессий и этапов.
     * Находится по турниру и сезону, id источника у него нет.
     *
     * @param  array{sport: Sport, category: Category, competition: Competition, edition: Edition|null, stage: Stage|null, national: bool, discipline: Discipline|null}  $context
     */
    private function parentEvent(array $context, CarbonImmutable $start): Event
    {
        $competition = $context['competition'];
        $parent = Event::query()
            ->where('competition_id', $competition->id)
            ->where('edition_id', $context['edition']?->id)
            ->whereNull('parent_id')
            ->whereIn('kind', ['meeting', 'tournament'])
            ->first();

        return $parent ?? $this->writer->event($competition, array_filter([
            'edition_id' => $context['edition']?->id,
            'kind' => $context['sport']->key === 'cycling' ? 'tournament' : 'meeting',
            'name' => $competition->name,
            'date' => $start->setTimezone(config('sports.timezone'))->toDateString(),
            'starts_at' => $start,
            'time_precision' => 'exact',
            'status' => 'scheduled',
        ], fn ($value) => $value !== null));
    }

    /** Родитель: от первой до последней сессии; завершён, когда завершены все. */
    private function refreshParent(Event $parent): void
    {
        $children = Event::query()->where('parent_id', $parent->id)->get();
        $statuses = $children->pluck('status')->unique();
        $parent->update([
            'starts_at' => $children->min('starts_at'),
            'ends_at' => $children->max(fn (Event $child) => $child->ends_at ?? $child->starts_at),
            'date' => $children->min('date'),
            'venue' => $children->pluck('venue')->filter()->first(),
            'status' => match (true) {
                $statuses->contains('live') => 'live',
                $statuses->every(fn (string $status) => $status === 'scheduled') => 'scheduled',
                // Часть сессий прошла, часть впереди — этап идёт.
                $statuses->contains('finished') && $statuses->contains('scheduled') => 'live',
                // Ничего не впереди (отменённый этап — со статусом «неизвестно») — этап завершён.
                $statuses->contains('finished') => 'finished',
                default => 'unknown',
            },
        ]);
    }

    /**
     * @param  array{sport: Sport, category: Category, competition: Competition, edition: Edition|null, stage: Stage|null, national: bool, discipline: Discipline|null}  $context
     * @param  array{id: string, national?: bool}|null  $subject
     */
    private function teamEntry(array $context, string $id, ?string $name, ?string $code, ?string $logo, ?array $subject): ?Entry
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $national = $context['national'] || ($subject !== null && $subject['id'] === $id && ($subject['national'] ?? false));
        $attributes = ['name' => $name, 'code' => $code, 'logo_url' => $logo !== null && $logo !== '' ? self::LOGO_URL.$logo : null, 'discipline_id' => $context['discipline']?->id];
        if ($national) {
            $attributes += $this->nationalAttributes($name, $context['competition']);
        } elseif ($context['competition']->participant_kind === 'club') {
            $attributes['kind'] = 'club';
        }

        $team = $this->writer->team($context['sport'], array_filter($attributes, fn ($value) => $value !== null), [$this->provider, 'participant', $id, null]);
        $this->writer->name($team, 'en', $name, provider: $this->provider);

        return $this->writer->teamEntry($team);
    }

    /**
     * Сборная: признаки из обозначений Flashscore в названии («France W»,
     * «France U21», «France Ol.»), страна — по названию без обозначений.
     *
     * @return array<string, string|int|null>
     */
    private function nationalAttributes(string $name, Competition $competition): array
    {
        preg_match('~^(.*?)((?:\s+(?:W|U\d{2}|Ol\.))*)$~u', $name, $match);
        $base = trim($match[1] ?? $name);
        $marks = preg_split('~\s+~', trim($match[2] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $age = collect($marks)->first(fn (string $mark) => (bool) preg_match('~^U\d{2}$~', $mark));
        $iso = NationalTeams::parse($base)['code'] ?? null;

        return [
            'kind' => 'national',
            'gender' => in_array('W', $marks, true) ? 'women' : ($competition->gender ?? 'men'),
            'age_group' => $age !== null ? strtolower($age) : (in_array('Ol.', $marks, true) ? null : 'senior'),
            'squad' => in_array('Ol.', $marks, true) ? 'olympic' : 'first',
            'country_id' => $iso !== null ? Country::query()->where('iso2', strtoupper($iso))->orWhere('code', Names::slug($base))->value('id') : null,
        ];
    }

    /**
     * Спортсмен или пара: у каждого спортсмена свой id; пара — заявка из двух,
     * а не команда. Гражданство — как у источника (CA/GA…).
     *
     * @param  list<string>  $ids
     * @param  array<string, string>  $fields
     * @param  array{0: string, 1: string}  $memberNames
     * @param  array{0: string, 1: string}  $countryIds
     * @param  array{0: string, 1: string}  $countryNames
     */
    private function athleteEntry(Sport $sport, array $ids, array $fields, string $nameKey, array $memberNames, array $countryIds, array $countryNames): ?Entry
    {
        $athletes = [];
        foreach ($ids as $index => $id) {
            $name = count($ids) > 1 ? ($fields[$memberNames[$index]] ?? null) : ($fields[$nameKey] ?? null);
            if ($name === null || trim($name) === '' || $index > 1) {
                return null;
            }
            $athlete = $this->writer->athlete($sport, ['name' => $name], [$this->provider, 'participant', $id, null]);
            $this->writer->name($athlete, 'en', $name, provider: $this->provider);
            $country = $this->country($fields[$countryIds[$index]] ?? null, $fields[$countryNames[$index]] ?? null);
            if ($country !== null) {
                $this->writer->nationality($athlete, $country, $this->provider);
            }
            $athletes[] = $athlete;
        }

        return count($athletes) === 2
            ? $this->writer->pairEntry($sport, $athletes[0], $athletes[1])
            : $this->writer->athleteEntry($sport, $athletes[0]);
    }

    private function count(Competition $competition, Event $event, CarbonImmutable $start): void
    {
        $this->events++;
        $date = $event->date?->toDateString() ?? $start->toDateString();
        $window = $this->windows[$competition->id] ?? [$date, $date];
        $this->windows[$competition->id] = [min($window[0], $date), max($window[1], $date)];
    }

    /**
     * «id» или «id/id» (пара).
     *
     * @return list<string>
     */
    private function ids(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $value), fn (string $id) => (bool) preg_match('~^[A-Za-z0-9]{8}$~', $id)));
    }

    /** Пол — из адреса раздела (atp/wta, …-men, …-women) или «Women» в названии. */
    private function gender(string $categoryKey, string $name): ?string
    {
        return match (true) {
            str_contains($categoryKey, 'mixed') => 'mixed',
            str_starts_with($categoryKey, 'wta') || (bool) preg_match('~(^|-)women$~', $categoryKey) || (bool) preg_match('~\bWomen\b~i', $name) => 'women',
            str_starts_with($categoryKey, 'atp') || (bool) preg_match('~(^|-)men$~', $categoryKey) => 'men',
            default => null,
        };
    }

    private function model(string $entity, int $id): ?CatalogModel
    {
        return match ($entity) {
            'competition' => Competition::query()->find($id),
            'category' => Category::query()->find($id),
            'country' => Country::query()->find($id),
            'team' => Team::query()->find($id),
            'athlete' => Athlete::query()->find($id),
            default => null,
        };
    }
}
