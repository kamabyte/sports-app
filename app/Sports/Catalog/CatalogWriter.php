<?php

namespace App\Sports\Catalog;

use App\Sports\CatalogDatabase;
use App\Sports\Models\Asset;
use App\Sports\Models\Athlete;
use App\Sports\Models\AthleteNationality;
use App\Sports\Models\CatalogModel;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Country;
use App\Sports\Models\Discipline;
use App\Sports\Models\Edition;
use App\Sports\Models\EditionEntry;
use App\Sports\Models\Entry;
use App\Sports\Models\EntryMember;
use App\Sports\Models\Event;
use App\Sports\Models\EventEntry;
use App\Sports\Models\Name;
use App\Sports\Models\Provider;
use App\Sports\Models\Region;
use App\Sports\Models\Sport;
use App\Sports\Models\Stage;
use App\Sports\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Запись в каталог — одна для импорта и демо-данных. Проверяет правила
 * каталога (вид спорта совпадает, разделы без циклов, пара — два разных
 * спортсмена, спортсмен не играет против себя) и никогда не стирает
 * известное: пустое поле из «лёгкого» ответа источника не затирает значение.
 *
 * Источник сущности — [Provider, scope, id, url?]: по нему сущность находится
 * при повторном импорте, даже если её переименовали.
 */
final class CatalogWriter
{
    /** Сущности, у которых есть названия в catalog_names. */
    public const array MORPHS = [
        'sport' => Sport::class,
        'region' => Region::class,
        'country' => Country::class,
        'category' => Category::class,
        'team' => Team::class,
        'athlete' => Athlete::class,
        'competition' => Competition::class,
    ];

    private const array CATEGORY_KINDS = ['country', 'region', 'tour', 'series', 'discipline', 'weight_class', 'game', 'promotion', 'international', 'other'];

    private const array EVENT_STATUSES = ['scheduled', 'live', 'finished', 'postponed', 'cancelled', 'abandoned', 'interrupted', 'unknown'];

    /** @var array<string, Sport> */
    private array $sports = [];

    public function provider(string $key, string $name, ?string $homepage = null): Provider
    {
        return Provider::query()->firstOrCreate(['key' => $key], ['name' => $name, 'homepage' => $homepage]);
    }

    /** Все виды спорта из sports.catalog.sports — в порядке меню. */
    public function syncSports(): void
    {
        foreach (array_keys(config('sports.catalog.sports')) as $key) {
            $this->sport((string) $key);
        }
    }

    public function sport(string $key): Sport
    {
        if (isset($this->sports[$key])) {
            return $this->sports[$key];
        }

        $config = config("sports.catalog.sports.{$key}") ?? throw new InvalidCatalogData("Unknown sport {$key}");
        $position = array_search($key, array_keys(config('sports.catalog.sports')), true);

        $sport = Sport::query()->updateOrCreate(['key' => $key], [
            'name' => $config['name'],
            'family' => $config['family'],
            'wave' => $config['wave'],
            'position' => (int) $position,
        ]);
        $this->name($sport, 'ru', $config['name'], curated: true);

        return $this->sports[$key] = $sport;
    }

    public function discipline(Sport $sport, string $key, string $name): Discipline
    {
        return Discipline::query()->firstOrCreate(['sport_id' => $sport->id, 'key' => $key], ['name' => $name]);
    }

    public function region(string $key, string $name, ?string $russian = null): Region
    {
        $region = Region::query()->firstOrCreate(['key' => $key], ['name' => $name]);
        $this->name($region, 'en', $name);
        if ($russian !== null) {
            $this->name($region, 'ru', $russian, curated: true);
        }

        return $region;
    }

    /**
     * @param  array{kind?: string, iso2?: string|null, iso3?: string|null, parent_id?: int|null, region_id?: int|null, flag_url?: string|null}  $attributes
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    public function country(string $code, string $name, array $attributes = [], ?array $source = null): Country
    {
        $country = $this->bySource('country', $source, Country::class)
            ?? Country::query()->firstOrNew(['code' => $code]);

        $flag = isset($attributes['flag_url']) ? $this->asset('flag', $attributes['flag_url'], null) : null;
        unset($attributes['flag_url']);

        $this->fill($country, ['name' => $name, 'kind' => $attributes['kind'] ?? null, 'flag_asset_id' => $flag?->id] + $attributes);
        if (! $country->exists) {
            $country->code = $code;
            $country->kind ??= 'country';
        }
        $country->save();
        $this->linkSource('country', $source, $country->id);

        return $country;
    }

    /**
     * Раздел навигации. Раздел-страна ссылается на страну, раздел-регион — на
     * регион; тур, весовая категория, игра — ни на что: это не география.
     */
    public function category(Sport $sport, string $kind, string $key, string $name, ?Country $country = null, ?Region $region = null, ?Category $parent = null, ?int $position = null): Category
    {
        if (in_array($key, Display::RESERVED_KEYS, true)) {
            throw new InvalidCatalogData("Category key {$key} is reserved for sport pages");
        }
        if (! in_array($kind, self::CATEGORY_KINDS, true)) {
            throw new InvalidCatalogData("Unknown category kind {$kind}");
        }
        if (($kind === 'country') !== ($country !== null) || ($kind === 'region') !== ($region !== null)) {
            throw new InvalidCatalogData("Category {$key}: kind {$kind} does not match its country/region reference");
        }

        $category = Category::query()->firstOrNew(['sport_id' => $sport->id, 'key' => $key]);
        $category->fill([
            'kind' => $kind,
            'name' => $name,
            'country_id' => $country?->id,
            'region_id' => $region?->id,
        ]);
        if ($position !== null) {
            $category->position = $position;
        }
        if ($parent !== null) {
            $this->assertParentCategory($category, $parent, $sport);
            $category->parent_id = $parent->id;
        }
        $category->save();

        return $category;
    }

    /**
     * Название на языке. Ручную правку (curated) импорт не перезаписывает и не
     * дублирует. Перевод — только наблюдённый у источника или заведённый вручную.
     */
    public function name(CatalogModel $entity, string $locale, string $name, string $kind = 'official', ?Provider $provider = null, ?string $url = null, bool $curated = false): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $type = array_search($entity::class, self::MORPHS, true) ?: throw new InvalidCatalogData('Entity '.$entity::class.' has no names');
        $existing = Name::query()
            ->where('nameable_type', $type)
            ->where('nameable_id', $entity->getKey())
            ->where('locale', $locale)
            ->where('kind', $kind);

        // Одно официальное название на язык: новое заменяет старое, но не ручное.
        if ($kind === 'official') {
            $current = (clone $existing)->first();
            if ($current !== null && ($current->curated && ! $curated)) {
                return;
            }
            if ($current !== null) {
                $current->update([
                    'name' => $name,
                    'normalized' => Names::normalize($name),
                    'provider_id' => $provider->id ?? $current->getAttribute('provider_id'),
                    'source_url' => $url ?? $current->getAttribute('source_url'),
                    'curated' => $curated || $current->curated,
                    'observed_at' => $provider !== null ? CarbonImmutable::now() : $current->getAttribute('observed_at'),
                ]);

                return;
            }
        } elseif ((clone $existing)->where('name', $name)->exists()) {
            return;
        }

        Name::query()->create([
            'nameable_type' => $type,
            'nameable_id' => $entity->getKey(),
            'locale' => $locale,
            'kind' => $kind,
            'name' => $name,
            'normalized' => Names::normalize($name),
            'provider_id' => $provider?->id,
            'source_url' => $url,
            'curated' => $curated,
            'observed_at' => $provider !== null ? CarbonImmutable::now() : null,
        ]);
    }

    public function asset(string $kind, ?string $url, ?Provider $provider): ?Asset
    {
        if ($url === null || ! preg_match('~^https://[^\s"\'<>]+$~', $url)) {
            return null;
        }

        $asset = Asset::query()->firstOrCreate(['kind' => $kind, 'url' => $url], ['provider_id' => $provider?->id]);
        $asset->update(['observed_at' => CarbonImmutable::now()]);

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $attributes  name, short_name, code, kind, gender, age_group, squad, country_id, logo_url, identity_state
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    public function team(Sport $sport, array $attributes, ?array $source = null): Team
    {
        $team = $this->bySource('team', $source, Team::class) ?? new Team(['sport_id' => $sport->id]);
        if ($team->sport_id !== $sport->id) {
            throw new InvalidCatalogData("Team #{$team->id} belongs to another sport");
        }

        $logo = isset($attributes['logo_url']) ? $this->asset('logo', $attributes['logo_url'], $source[0] ?? null) : null;
        unset($attributes['logo_url']);
        $this->fill($team, $attributes + ['logo_asset_id' => $logo?->id]);
        $team->kind ??= 'unknown';
        $team->slug = Names::slug($team->name);
        $team->identity_state = $source !== null ? 'verified' : ($team->identity_state ?? 'provisional');
        $team->save();
        $this->linkSource('team', $source, $team->id);

        return $team;
    }

    /**
     * @param  array<string, mixed>  $attributes  name, short_name, gender, birth_date, portrait_url
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    public function athlete(Sport $sport, array $attributes, ?array $source = null): Athlete
    {
        $athlete = $this->bySource('athlete', $source, Athlete::class) ?? new Athlete;

        $portrait = isset($attributes['portrait_url']) ? $this->asset('portrait', $attributes['portrait_url'], $source[0] ?? null) : null;
        unset($attributes['portrait_url']);
        $this->fill($athlete, $attributes + ['portrait_asset_id' => $portrait?->id]);
        $athlete->slug = Names::slug($athlete->name);
        $athlete->identity_state = $source !== null ? 'verified' : ($athlete->identity_state ?? 'provisional');
        $athlete->save();
        $this->linkSource('athlete', $source, $athlete->id);

        CatalogDatabase::connection()->table('catalog_athlete_sports')->insertOrIgnore([
            'athlete_id' => $athlete->id, 'sport_id' => $sport->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $athlete;
    }

    /** Флаг, под которым спортсмен выступает, — как его показал источник. */
    public function nationality(Athlete $athlete, Country $country, ?Provider $provider): void
    {
        AthleteNationality::query()->updateOrCreate(
            ['athlete_id' => $athlete->id, 'country_id' => $country->id, 'provider_id' => $provider?->id],
            ['observed_at' => CarbonImmutable::now()],
        );
    }

    public function teamEntry(Team $team): Entry
    {
        return Entry::query()->firstOrCreate(['resolution_key' => "team:{$team->id}"], [
            'sport_id' => $team->sport_id,
            'kind' => 'team',
            'team_id' => $team->id,
        ]);
    }

    public function athleteEntry(Sport $sport, Athlete $athlete): Entry
    {
        return $this->membersEntry($sport, 'individual', [$athlete]);
    }

    /**
     * Пара — отдельная заявка из двух спортсменов; та же пара в другом турнире —
     * та же заявка, другой партнёр — другая. Порядок членов — как у источника.
     */
    public function pairEntry(Sport $sport, Athlete $first, Athlete $second): Entry
    {
        if ($first->id === $second->id) {
            throw new InvalidCatalogData("Pair needs two different athletes, got #{$first->id} twice");
        }

        return $this->membersEntry($sport, 'pair', [$first, $second]);
    }

    /**
     * @param  list<Athlete>  $athletes
     */
    public function relayEntry(Sport $sport, array $athletes, ?Team $team = null): Entry
    {
        if (count($athletes) < 2) {
            throw new InvalidCatalogData('Relay needs at least two athletes');
        }

        $entry = $this->membersEntry($sport, 'relay', $athletes);
        if ($team !== null && $entry->team_id === null) {
            $entry->update(['team_id' => $team->id]);
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    public function competition(Sport $sport, Category $category, array $attributes, ?array $source = null): Competition
    {
        if ($category->sport_id !== $sport->id) {
            throw new InvalidCatalogData("Category {$category->key} belongs to another sport");
        }

        $competition = $this->bySource('competition', $source, Competition::class)
            ?? Competition::query()->firstOrNew(['primary_category_id' => $category->id, 'slug' => $attributes['slug'] ?? Names::slug((string) $attributes['name'])]);
        if ($competition->exists && $competition->sport_id !== $sport->id) {
            throw new InvalidCatalogData("Competition #{$competition->id} belongs to another sport");
        }

        $logo = isset($attributes['logo_url']) ? $this->asset('logo', $attributes['logo_url'], $source[0] ?? null) : null;
        unset($attributes['logo_url']);
        $competition->sport_id = $sport->id;
        $competition->primary_category_id ??= $category->id;
        $this->fill($competition, $attributes + ['logo_asset_id' => $logo?->id]);
        $competition->slug ??= Names::slug($competition->name);
        $competition->identity_state = $source !== null ? 'verified' : ($competition->identity_state ?? 'provisional');
        $competition->save();
        $this->linkSource('competition', $source, $competition->id);
        $this->place($competition, $category);

        return $competition;
    }

    /** Ещё один раздел, где виден турнир (не копия турнира). */
    public function place(Competition $competition, Category $category, ?int $position = null): void
    {
        if ($category->sport_id !== $competition->sport_id) {
            throw new InvalidCatalogData("Competition #{$competition->id} cannot be placed into category of another sport");
        }

        CatalogDatabase::connection()->table('catalog_competition_category')->updateOrInsert(
            ['competition_id' => $competition->id, 'category_id' => $category->id],
            ['position' => $position ?? $competition->position ?? 1000, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes  label, starts_on, ends_on, is_current
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    public function edition(Competition $competition, string $key, array $attributes, ?array $source = null): Edition
    {
        $edition = $this->bySource('edition', $source, Edition::class)
            ?? Edition::query()->firstOrNew(['competition_id' => $competition->id, 'key' => $key]);
        if ($edition->exists && $edition->competition_id !== $competition->id) {
            throw new InvalidCatalogData("Edition #{$edition->id} belongs to another competition");
        }

        $edition->competition_id = $competition->id;
        $edition->key ??= $key;
        $this->fill($edition, $attributes);
        $edition->label ??= $key;
        $edition->save();
        $this->linkSource('edition', $source, $edition->id);

        if ($edition->is_current) {
            Edition::query()->where('competition_id', $competition->id)->whereKeyNot($edition->id)->update(['is_current' => false]);
        }

        return $edition;
    }

    /**
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    public function stage(Edition $edition, string $kind, string $name, int $position = 0, ?Stage $parent = null, ?array $source = null): Stage
    {
        if ($parent !== null && $parent->edition_id !== $edition->id) {
            throw new InvalidCatalogData('Stage parent belongs to another edition');
        }

        $stage = $this->bySource('stage', $source, Stage::class)
            ?? Stage::query()->firstOrNew(['edition_id' => $edition->id, 'parent_id' => $parent?->id, 'name' => $name]);
        $stage->fill(['edition_id' => $edition->id, 'parent_id' => $parent?->id, 'kind' => $kind, 'name' => $name, 'position' => $position]);
        $stage->save();
        $this->linkSource('stage', $source, $stage->id);

        return $stage;
    }

    /**
     * Событие. Повторный импорт находит его по id источника; завершённое не
     * откатывается к «запланировано» из-за устаревшей страницы.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    public function event(Competition $competition, array $attributes, ?array $source = null): Event
    {
        $event = $this->bySource('event', $source, Event::class) ?? new Event;
        if ($event->exists && $event->competition_id !== $competition->id) {
            $previous = Competition::query()->find($event->competition_id);
            if ($previous?->sport_id !== $competition->sport_id) {
                throw new InvalidCatalogData("Event #{$event->id} belongs to another sport");
            }
        }

        $status = $attributes['status'] ?? null;
        if ($status !== null && ! in_array($status, self::EVENT_STATUSES, true)) {
            throw new InvalidCatalogData("Unknown event status {$status}");
        }
        if ($event->exists && $event->status === 'finished' && in_array($status, ['scheduled', 'unknown'], true)) {
            unset($attributes['status'], $attributes['status_detail'], $attributes['raw_status']);
        }

        $this->assertEventLinks($competition, $attributes);
        $event->competition_id = $competition->id;
        $this->fill($event, $attributes);
        $event->kind ??= 'match';
        $event->last_observed_at = CarbonImmutable::now();
        $event->save();
        $this->linkSource('event', $source, $event->id);

        return $event;
    }

    /**
     * Участники события. Сторона — [side, entry|null, label?, home_away?, seed?,
     * entry_status?, affiliation?, position?]. У очной встречи — не больше двух
     * сторон, и один спортсмен не может быть по обе стороны.
     *
     * @param  list<array{side?: int|null, entry?: Entry|null, label?: string|null, home_away?: string|null, seed?: string|null, entry_status?: string|null, affiliation?: string|null, position?: int}>  $entries
     */
    public function eventEntries(Event $event, array $entries): void
    {
        $sport = Competition::query()->whereKey($event->competition_id)->value('sport_id');
        $athletesBySide = [];

        foreach ($entries as $item) {
            $entry = $item['entry'] ?? null;
            if ($entry !== null && $entry->sport_id !== $sport) {
                throw new InvalidCatalogData("Entry #{$entry->id} is from another sport than event #{$event->id}");
            }
            $side = $item['side'] ?? null;
            if ($side !== null && ! in_array($side, [1, 2], true)) {
                throw new InvalidCatalogData("Head-to-head side must be 1 or 2, got {$side}");
            }
            if ($side !== null && $entry !== null) {
                $athletesBySide[$side] = [...($athletesBySide[$side] ?? []), ...EntryMember::query()->where('entry_id', $entry->id)->pluck('athlete_id')->all()];
            }
        }
        if (array_intersect($athletesBySide[1] ?? [], $athletesBySide[2] ?? []) !== []) {
            throw new InvalidCatalogData("Athlete appears on both sides of event #{$event->id}");
        }

        DB::connection(CatalogDatabase::connectionName())->transaction(function () use ($event, $entries) {
            $keep = [];
            foreach ($entries as $index => $item) {
                $entry = $item['entry'] ?? null;
                $side = $item['side'] ?? null;
                $match = $side !== null
                    ? ['event_id' => $event->id, 'side' => $side]
                    : ['event_id' => $event->id, 'entry_id' => $entry?->id];
                $row = EventEntry::query()->firstOrNew($match);
                // Неизвестный соперник позже становится известным; известный не стирается пустым.
                if ($entry !== null) {
                    $row->entry_id = $entry->id;
                    if ($row->entry_status === 'unlisted') {
                        $row->entry_status = null;
                    }
                }
                $this->fill($row, [
                    'label' => $item['label'] ?? null,
                    'home_away' => $item['home_away'] ?? null,
                    'seed' => $item['seed'] ?? null,
                    'entry_status' => $item['entry_status'] ?? null,
                    'affiliation' => $item['affiliation'] ?? null,
                ]);
                $row->position = $item['position'] ?? $index;
                $row->save();
                $keep[] = $row->id;
            }

            // Состав гонки — как в последнем полном наблюдении; у очной встречи стороны постоянны.
            if ($entries !== [] && ($entries[0]['side'] ?? null) === null) {
                EventEntry::query()->where('event_id', $event->id)->whereNotIn('id', $keep)->delete();
            }
        });
    }

    public function editionEntry(Edition $edition, Entry $entry, ?string $affiliation = null): void
    {
        $row = EditionEntry::query()->firstOrNew(['edition_id' => $edition->id, 'entry_id' => $entry->id]);
        $this->fill($row, ['affiliation' => $affiliation]);
        $row->save();
    }

    /**
     * @param  list<Athlete>  $athletes
     */
    private function membersEntry(Sport $sport, string $kind, array $athletes): Entry
    {
        $key = $kind.':'.implode('-', array_map(fn (Athlete $athlete) => $athlete->id, $athletes));

        return DB::connection(CatalogDatabase::connectionName())->transaction(function () use ($sport, $kind, $athletes, $key) {
            $entry = Entry::query()->firstOrCreate(['resolution_key' => $key], ['sport_id' => $sport->id, 'kind' => $kind]);
            if ($entry->sport_id !== $sport->id) {
                throw new InvalidCatalogData("Entry {$key} belongs to another sport");
            }
            foreach ($athletes as $position => $athlete) {
                EntryMember::query()->firstOrCreate(['entry_id' => $entry->id, 'athlete_id' => $athlete->id], ['position' => $position + 1]);
            }

            return $entry;
        });
    }

    /**
     * Сезон — этого турнира, стадия — этого сезона, родитель — без циклов и того же вида спорта.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function assertEventLinks(Competition $competition, array $attributes): void
    {
        $editionId = $attributes['edition_id'] ?? null;
        if ($editionId !== null && Edition::query()->whereKey($editionId)->value('competition_id') !== $competition->id) {
            throw new InvalidCatalogData("Edition #{$editionId} is not an edition of competition #{$competition->id}");
        }
        $stageId = $attributes['stage_id'] ?? null;
        if ($stageId !== null && Stage::query()->whereKey($stageId)->value('edition_id') !== $editionId) {
            throw new InvalidCatalogData("Stage #{$stageId} is not a stage of edition #{$editionId}");
        }
        $parentId = $attributes['parent_id'] ?? null;
        if ($parentId !== null) {
            $parent = Event::query()->with('competition')->whereKey($parentId)->first() ?? throw new InvalidCatalogData("Parent event #{$parentId} not found");
            if ($parent->competition->sport_id !== $competition->sport_id) {
                throw new InvalidCatalogData('Parent event is from another sport');
            }
            if ($parent->parent_id !== null) {
                throw new InvalidCatalogData('Event hierarchy is limited to one level (meeting → session, tie → rubber)');
            }
        }
    }

    private function assertParentCategory(Category $category, Category $parent, Sport $sport): void
    {
        if ($parent->sport_id !== $sport->id) {
            throw new InvalidCatalogData("Parent category {$parent->key} belongs to another sport");
        }

        for ($node = $parent, $depth = 0; $node !== null; $node = $node->parent_id ? Category::query()->find($node->parent_id) : null, $depth++) {
            if (($category->exists && $node->id === $category->id) || $depth > 16) {
                throw new InvalidCatalogData("Category {$category->key} would form a cycle");
            }
        }
    }

    /**
     * Найти сущность по id источника.
     *
     * @template T of CatalogModel
     *
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function bySource(string $entity, ?array $source, string $class): ?CatalogModel
    {
        if ($source === null) {
            return null;
        }

        $id = SourceIds::find($entity, $source[0]->id, $source[1], $source[2]);

        return $id === null ? null : $class::query()->find($id);
    }

    /**
     * @param  array{0: Provider, 1: string, 2: string, 3?: string|null}|null  $source
     */
    private function linkSource(string $entity, ?array $source, int $id): void
    {
        if ($source !== null) {
            SourceIds::link($entity, $source[0]->id, $source[1], $source[2], $id, $source[3] ?? null);
        }
    }

    /**
     * Заполнить только известные значения: null из нового наблюдения не
     * стирает старое.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fill(CatalogModel $model, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $model->setAttribute($key, $value);
            }
        }
    }
}
