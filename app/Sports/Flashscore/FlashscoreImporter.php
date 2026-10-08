<?php

namespace App\Sports\Flashscore;

use App\Sports\Catalog\CatalogWriter;
use App\Sports\CatalogDatabase;
use App\Sports\Models\Competition;
use App\Sports\Models\Coverage;
use App\Sports\Models\Event;
use App\Sports\Models\ImportRun;
use App\Sports\Models\Observation;
use App\Sports\Models\Page;
use App\Sports\Models\Provider;
use App\Sports\Models\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Импорт каталога из публичных страниц Flashscore по списку из
 * sports.catalog.flashscore: страницы турниров и команд, сначала английская
 * (сущности, события, счёт), потом русская (только названия по id).
 *
 * Каждая страница — своя транзакция: сбой одной не трогает остальные и не
 * стирает уже известное. Повторный и параллельный запуск не создаёт
 * дублей: сущности находятся по id источника, запуск — под блокировкой.
 * Пробный запуск (dry run) всё проверяет и откатывает.
 */
final class FlashscoreImporter
{
    private const string LOCK = 'catalog:import:flashscore';

    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly CatalogWriter $writer = new CatalogWriter,
    ) {}

    /**
     * @param  list<string>|null  $sports  только эти виды спорта
     * @param  list<string>|null  $paths  только эти страницы
     */
    public function run(?array $sports = null, ?array $paths = null, bool $dryRun = false, bool $translations = true, bool $planned = false): ImportRun
    {
        $lock = Cache::lock(self::LOCK, 3600);
        if (! $lock->get()) {
            throw new LockTimeoutException('Another catalog import is running');
        }

        try {
            return $this->runLocked($sports, $paths, $dryRun, $translations, $planned);
        } finally {
            $lock->release();
        }
    }

    /**
     * Страницы из настроек: [вид спорта, адрес, тема-команда|null].
     *
     * @param  list<string>|null  $sports
     * @param  list<string>|null  $paths
     * @return list<array{sport: string, path: string, subject: array{id: string, national?: bool}|null}>
     */
    public static function pages(?array $sports = null, ?array $paths = null): array
    {
        $config = config('sports.catalog.flashscore');
        $pages = [];
        foreach ($config['competitions'] ?? [] as $sport => $list) {
            foreach ($list as $path) {
                $pages[] = ['sport' => $sport, 'path' => $path, 'subject' => null];
            }
        }
        foreach ($config['teams'] ?? [] as $sport => $list) {
            foreach ($list as $team) {
                preg_match('~/team/[a-z0-9-]+/([A-Za-z0-9]{8})/$~', $team['path'], $match);
                $pages[] = ['sport' => $sport, 'path' => $team['path'], 'subject' => isset($match[1]) ? ['id' => $match[1], 'national' => (bool) ($team['national'] ?? false)] : null];
            }
        }

        return array_values(array_filter($pages, fn (array $page) => ($sports === null || in_array($page['sport'], $sports, true))
            && ($paths === null || in_array($page['path'], $paths, true))));
    }

    /**
     * @param  list<string>|null  $sports
     * @param  list<string>|null  $paths
     */
    private function runLocked(?array $sports, ?array $paths, bool $dryRun, bool $translations, bool $planned): ImportRun
    {
        $connection = CatalogDatabase::connection();
        $provider = $this->writer->provider('flashscore', 'Flashscore', 'https://www.flashscore.com');
        $this->writer->syncSports();
        $this->register($provider);
        $diagnostics = [];

        // Плановый запуск: сначала дневные списки (кто играет сегодня-завтра), потом страницы в пределах бюджета.
        [$liteFetched, $liteCached] = [0, 0];
        if ($planned && ! $dryRun && config('sports.catalog.flashscore.lite.enabled')) {
            $lite = new LiteDayImporter($this->writer, $provider, (float) config('sports.catalog.flashscore.pause_seconds'), (int) config('sports.catalog.flashscore.lite.cache_minutes'));
            $lite->import(array_keys(LiteDayImporter::sections()), config('sports.catalog.flashscore.lite.days'));
            $diagnostics = [...$diagnostics, ...$lite->diagnostics];
            $diagnostics[] = "day lists: {$lite->fetched} fetched, {$lite->cached} from cache; {$lite->updated} events refreshed, {$lite->created} new provisional events, {$lite->withoutKickoff} live without a known kickoff skipped, ".count($lite->unresolved).' headers without a known page';
            $this->probes($provider, $lite->candidates);
            [$liteFetched, $liteCached] = [$lite->fetched, $lite->cached];
        }
        $pages = $planned ? $this->plan() : self::pages($sports, $paths);

        $run = ImportRun::query()->create([
            'provider_id' => $provider->id,
            'kind' => 'pages',
            'scope' => array_column($pages, 'path'),
            'dry_run' => $dryRun,
            'parser_version' => PageParser::VERSION,
            'started_at' => CarbonImmutable::now(),
        ]);
        $failed = 0;
        $succeeded = 0;
        $events = 0;

        foreach ($pages as $item) {
            $sport = $this->writer->sport($item['sport']);
            // Сначала загрузка (наблюдения сохраняются и при сбое), потом запись одной транзакцией.
            try {
                $english = $this->observe($run, $provider, 'en', $item['path']);
                $this->assertSport($english['page'], $sport);
            } catch (RateLimited $exception) {
                $diagnostics[] = "{$item['path']}: {$exception->getMessage()} — run stopped";
                $failed++;
                break;
            } catch (InvalidPage $exception) {
                $failed++;
                $diagnostics[] = "{$item['path']}: FAILED — {$exception->getMessage()}";
                $this->failure($provider, $item['path'], $exception->getMessage(), $dryRun);

                continue;
            }

            $russian = null;
            if ($translations && ($item['translate'] ?? true) && isset(config('sports.catalog.flashscore.hosts')['ru'])) {
                try {
                    $russian = $this->observe($run, $provider, 'ru', $item['path']);
                    if ($russian['page']->locale !== 'ru') {
                        $diagnostics[] = "{$item['path']}: ru page declared locale {$russian['page']->locale}, names skipped";
                        $russian = null;
                    }
                } catch (InvalidPage|RateLimited $exception) {
                    $diagnostics[] = "{$item['path']} (ru): {$exception->getMessage()} — Russian names kept from earlier runs";
                }
            }

            $connection->beginTransaction();
            try {
                $importer = new PageImporter($this->writer, $provider, config('sports.catalog.flashscore.national_competitions', []));
                $importer->import($english['page'], $sport, $english['url'], $item['subject']);
                $translated = $russian !== null ? $importer->names($russian['page'], $sport, 'ru', $russian['url']) : 0;

                $this->coverage($provider, $importer, null);
                if ($importer->events > 0) {
                    $sport->update(['active' => true]);
                }
                $events += $importer->events;
                foreach (array_slice($importer->skipped, 0, 20) as $skip) {
                    $diagnostics[] = "{$item['path']}: {$skip}";
                }
                $diagnostics[] = "{$item['path']}: {$importer->events} events, {$translated} Russian names";

                $dryRun ? $connection->rollBack() : $connection->commit();
                $succeeded++;
                if (! $dryRun) {
                    // Подтвердившийся адрес-кандидат — обычная страница реестра.
                    $done = ['imported_at' => CarbonImmutable::now(), 'error' => null, 'failed_at' => null];
                    if (Page::query()->where('path', $item['path'])->value('origin') === 'probe') {
                        $done['origin'] = 'lite';
                    }
                    if ($russian !== null) {
                        $done['translated_at'] = CarbonImmutable::now();
                    }
                    Page::query()->where('path', $item['path'])->update($done);
                    $this->popularity($provider, $item['path']);
                }
            } catch (Throwable $exception) {
                $connection->rollBack();
                $failed++;
                $diagnostics[] = "{$item['path']}: FAILED — {$exception->getMessage()}";
                $this->failure($provider, $item['path'], $exception->getMessage(), $dryRun);
            }
        }

        $run->update([
            'status' => match (true) {
                $failed === 0 => 'succeeded',
                $succeeded > 0 => 'partial',
                default => 'failed',
            },
            'pages_fetched' => $this->fetcher->fetched + $liteFetched,
            'pages_cached' => $this->fetcher->cached + $liteCached,
            'pages_failed' => $failed,
            'events_seen' => $events,
            'diagnostics' => $diagnostics,
            'finished_at' => CarbonImmutable::now(),
        ]);

        return $run;
    }

    /**
     * Загрузить страницу и записать наблюдение: адрес, язык, статус, хэш.
     *
     * @return array{page: ParsedPage, url: string, status: int|null, from_cache: bool, hash: string}
     */
    private function observe(ImportRun $run, Provider $provider, string $locale, string $path): array
    {
        $url = $this->fetcher->url($locale, $path);
        try {
            $result = $this->fetcher->get($locale, $path);
        } catch (InvalidPage|RateLimited $exception) {
            Observation::query()->create([
                'import_run_id' => $run->id, 'provider_id' => $provider->id, 'url' => $url, 'host' => (string) parse_url($url, PHP_URL_HOST),
                'status' => $exception instanceof RateLimited ? 'error' : 'invalid', 'error' => mb_substr($exception->getMessage(), 0, 250),
                'parser_version' => PageParser::VERSION, 'observed_at' => CarbonImmutable::now(),
            ]);
            throw $exception;
        }

        Observation::query()->create([
            'import_run_id' => $run->id, 'provider_id' => $provider->id, 'url' => $url, 'host' => (string) parse_url($url, PHP_URL_HOST),
            'locale' => $result['page']->locale, 'http_status' => $result['status'], 'status' => 'valid', 'content_hash' => $result['hash'],
            'parser_version' => PageParser::VERSION, 'records' => $result['page']->eventCount(), 'from_cache' => $result['from_cache'],
            'observed_at' => CarbonImmutable::now(),
        ]);

        return $result;
    }

    /**
     * Турниры из настроек — «популярные»: их порядок в настройках задаёт место
     * турнира и его раздела в списках; остальные идут ниже, по алфавиту.
     */
    private function popularity(Provider $provider, string $path): void
    {
        $configured = array_values(array_unique(array_column(self::pages(), 'path')));
        $index = array_search($path, $configured, true);
        if ($index === false || str_starts_with($path, '/team/')) {
            return;
        }

        $competitionId = CatalogDatabase::connection()->table('catalog_competition_sources')
            ->where('provider_id', $provider->id)
            ->where('url', rtrim((string) config('sports.catalog.flashscore.hosts.en'), '/').$path)
            ->value('competition_id');
        $competition = $competitionId !== null ? Competition::query()->whereKey($competitionId)->first() : null;
        if ($competition === null) {
            return;
        }
        $position = 10 + (int) $index;
        $competition->update(['position' => min($competition->position, $position)]);
        $category = $competition->primaryCategory;
        $category->update(['position' => min($category->position, $position)]);
    }

    /** Страницы из настроек — в реестре (origin config), чтобы учитывать, когда они загружались. */
    private function register(Provider $provider): void
    {
        $now = CarbonImmutable::now();
        $sports = CatalogDatabase::connection()->table('catalog_sports')->pluck('id', 'key');
        foreach (self::pages() as $page) {
            CatalogDatabase::connection()->table('catalog_pages')->updateOrInsert(
                ['path' => $page['path']],
                ['provider_id' => $provider->id, 'sport_id' => $sports[$page['sport']], 'origin' => 'config', 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    /**
     * Турниры дневного списка без адреса в карте сайта: адрес-кандидат
     * проверяется загрузкой страницы (в бюджете запуска). Подтвердился —
     * становится обычной страницей реестра; нет — помечается сбоем.
     *
     * @param  array<string, array{sport: string, paths: array<string, string>}>  $candidates
     */
    private function probes(Provider $provider, array $candidates): void
    {
        $sports = CatalogDatabase::connection()->table('catalog_sports')->pluck('id', 'key');
        $today = CarbonImmutable::now(config('sports.timezone'))->toDateString();
        foreach ($candidates as $header => $candidate) {
            // Короткое название — без стадии, места и покрытия («Copa Chile», «Braga»).
            $paths = $candidate['paths'];
            uasort($paths, fn (string $a, string $b) => mb_strlen($a) <=> mb_strlen($b));
            $path = array_key_first($paths);
            if (! is_string($path)) {
                continue;
            }
            $existing = Page::query()->where('path', $path)->first();
            if ($existing === null) {
                Page::query()->create(['provider_id' => $provider->id, 'sport_id' => $sports[$candidate['sport']], 'path' => $path, 'origin' => 'probe', 'listed_as' => $header, 'listed_on' => $today]);
            } elseif ($existing->origin === 'probe') {
                $existing->update(['listed_on' => $today, 'listed_as' => $header]);
            }
        }
    }

    /**
     * Что загрузить в плановый запуск (не больше pages_per_run): страницы из
     * настроек и турниры, которые играют сегодня-завтра по дневным спискам, —
     * давно не загружавшиеся первыми. Свежие (моложе cache_hours) пропускаются.
     * Русские названия — раз в translate_days.
     *
     * @return list<array{sport: string, path: string, subject: array{id: string, national?: bool}|null, translate: bool}>
     */
    private function plan(): array
    {
        $config = config('sports.catalog.flashscore');
        $fresh = CarbonImmutable::now()->subHours(max(1, (int) $config['cache_hours']));
        $translateBefore = CarbonImmutable::now()->subDays((int) $config['translate_days']);
        $today = CarbonImmutable::now(config('sports.timezone'))->toDateString();
        $subjects = collect(self::pages())->keyBy('path');

        return array_values(Page::query()
            ->with('sport')
            ->where(fn ($query) => $query->where('origin', 'config')->orWhere('listed_on', '>=', $today))
            ->where(fn ($query) => $query->whereNull('imported_at')->orWhere('imported_at', '<', $fresh))
            // Проверка адреса, не подтвердившегося за неделю, не повторяется.
            ->where(fn ($query) => $query->whereNot('origin', 'probe')->orWhereNull('failed_at')->orWhere('failed_at', '<', CarbonImmutable::now()->subDays(7)))
            ->orderByRaw("origin = 'config' desc")
            ->orderByRaw("origin = 'probe' desc")
            ->orderByRaw('imported_at is not null, imported_at')
            ->limit((int) $config['pages_per_run'])
            ->get()
            ->map(fn (Page $page) => [
                'sport' => $page->sport->key,
                'path' => $page->path,
                'subject' => $subjects->get($page->path)['subject'] ?? null,
                'translate' => $page->translated_at === null || $page->translated_at->lt($translateBefore),
            ])
            ->values()
            ->all());
    }

    /** Страница должна быть того же вида спорта, что в настройках (SA — вид спорта у Flashscore). */
    private function assertSport(ParsedPage $page, Sport $sport): void
    {
        $expected = array_map('strval', (array) config("sports.catalog.flashscore.sport_ids.{$sport->key}", []));
        if ($expected !== [] && $page->sportId !== null && ! in_array($page->sportId, $expected, true)) {
            throw new InvalidPage("Page sport {$page->sportId} does not match {$sport->key} (".implode(', ', $expected).')');
        }
    }

    private function coverage(Provider $provider, PageImporter $importer, ?string $error): void
    {
        foreach ($importer->windows as $competitionId => [$from, $to]) {
            $coverage = Coverage::query()->firstOrNew(['provider_id' => $provider->id, 'competition_id' => $competitionId]);
            $coverage->fill([
                'last_success_at' => CarbonImmutable::now(),
                'events' => Event::query()->where('competition_id', $competitionId)->count(),
                'window_from' => $coverage->window_from !== null && $coverage->window_from->toDateString() < $from ? $coverage->window_from : $from,
                'window_to' => $coverage->window_to !== null && $coverage->window_to->toDateString() > $to ? $coverage->window_to : $to,
                'last_error' => $error,
            ])->save();
        }
    }

    /** Сбой страницы турнира — отметка у турнира (если он уже известен), данные не трогаются. */
    private function failure(Provider $provider, string $path, string $message, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }
        Page::query()->where('path', $path)->update(['failed_at' => CarbonImmutable::now(), 'error' => mb_substr($message, 0, 250)]);

        $competitionId = CatalogDatabase::connection()->table('catalog_competition_sources')
            ->where('provider_id', $provider->id)
            ->where('url', 'like', '%'.$path)
            ->value('competition_id');
        if ($competitionId !== null) {
            Coverage::query()->updateOrCreate(
                ['provider_id' => $provider->id, 'competition_id' => $competitionId],
                ['last_failure_at' => CarbonImmutable::now(), 'last_error' => mb_substr($message, 0, 250)],
            );
        }
    }
}
