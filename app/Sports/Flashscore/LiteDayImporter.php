<?php

namespace App\Sports\Flashscore;

use App\Guide\Teams\NationalTeams;
use App\Sports\Catalog\CatalogWriter;
use App\Sports\Catalog\Names;
use App\Sports\Catalog\SourceIds;
use App\Sports\CatalogDatabase;
use App\Sports\Models\Category;
use App\Sports\Models\Competition;
use App\Sports\Models\Country;
use App\Sports\Models\Event;
use App\Sports\Models\Page;
use App\Sports\Models\Provider;
use App\Sports\Models\Sport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * «Сегодня» по всем турнирам: дневные списки лёгкой версии сайта. Матч
 * находится по id (тот же, что на странице турнира). Известный — получает
 * свежий статус (перенос, отмена, начало, окончание). Новый предстоящий —
 * создаётся предварительно: соперники —
 * подписи из списка (не команды каталога: у списка нет их id), турнир — по
 * адресу из реестра (карта сайта), без выдуманных адресов. Когда страница
 * турнира загрузится, то же событие получит настоящих участников.
 *
 * Заголовок «ENGLAND: Premier League» → адрес /football/england/premier-league/,
 * только если он есть в реестре; «Champions League - Preliminary» и
 * «Beijing (China), hard» сокращаются до известного адреса.
 */
final class LiteDayImporter
{
    private const string HOST = 'https://www.flashscore.mobi';

    private const string USER_AGENT = 'sports-app/1.0 (personal sports catalog)';

    private const string TIMEZONE = 'Europe/Prague';

    private const array LABELS = ['postponed' => 'postponed', 'abandoned' => 'abandoned', 'cancelled' => 'cancelled', 'canceled' => 'cancelled', 'interrupted' => 'interrupted'];

    public int $fetched = 0;

    public int $cached = 0;

    public int $created = 0;

    public int $updated = 0;

    /** @var list<string> */
    public array $diagnostics = [];

    /** @var array<string, int> заголовки без адреса в реестре → число матчей */
    public array $unresolved = [];

    /** @var array<string, array{sport: string, paths: array<string, string>}> заголовок → вид спорта и адреса-кандидаты для проверки */
    public array $candidates = [];

    /** Идущие матчи без известного начала (время скрыто прогрессом) — пропущены. */
    public int $withoutKickoff = 0;

    /** Разделы без дневного списка в лёгкой версии (проверено: 404 на 2026-10-08). */
    private const array NO_DAY_LIST = ['esports'];

    private ?float $lastRequestAt = null;

    public function __construct(
        private readonly CatalogWriter $writer,
        private readonly Provider $provider,
        private readonly float $pauseSeconds,
        private readonly int $cacheMinutes,
        private readonly LiteDayParser $parser = new LiteDayParser,
    ) {}

    /**
     * Раздел лёгкой версии по виду спорта; у гонок и гольфа нет списка «соперник — соперник».
     *
     * @return array<string, string>
     */
    public static function sections(): array
    {
        $sections = [];
        foreach (config('sports.catalog.sports') as $key => $sport) {
            if (in_array($sport['family'], ['race', 'golf'], true) || ! is_string($sport['flashscore']) || in_array($key, self::NO_DAY_LIST, true)) {
                continue;
            }
            $sections[(string) $key] = $key === 'football' ? '' : $sport['flashscore'];
        }

        return $sections;
    }

    /**
     * @param  list<string>  $sports
     * @param  list<int>  $offsets  дни относительно «сегодня» сайта
     */
    public function import(array $sports, array $offsets = [0, 1]): void
    {
        $sections = self::sections();
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        foreach ($sports as $key) {
            if (! array_key_exists($key, $sections)) {
                continue;
            }
            $sport = $this->writer->sport($key);
            foreach ($offsets as $offset) {
                $date = $today->addDays($offset);
                try {
                    $rows = $this->parser->parse($this->html($sections[$key], $offset, $date), $offset, $date);
                } catch (RateLimited $exception) {
                    $this->diagnostics[] = "lite {$key}: {$exception->getMessage()} — day lists stopped";

                    return;
                } catch (Throwable $exception) {
                    $this->diagnostics[] = "lite {$key} {$date->toDateString()}: {$exception->getMessage()}";

                    continue;
                }

                CatalogDatabase::connection()->transaction(fn () => $this->rows($sport, $rows, $date));
            }
        }
    }

    /**
     * @param  list<array{header: string, id: string, time: string|null, state: string, label: string|null, home: string, away: string, score: array{0: int, 1: int}|null, walkover: bool}>  $rows
     */
    private function rows(Sport $sport, array $rows, CarbonImmutable $date): void
    {
        $byHeader = [];
        foreach ($rows as $row) {
            $byHeader[$row['header']][] = $row;
        }

        foreach ($byHeader as $header => $items) {
            $resolved = $this->resolve($sport, $header);
            if ($resolved === null) {
                $this->unresolved["{$sport->key}: {$header}"] = count($items);
                $this->candidates[$header] = ['sport' => $sport->key, 'paths' => $this->paths($sport, $header)];

                continue;
            }
            [$path, $name] = $resolved;
            Page::query()->where('path', $path)->update(['listed_as' => $header, 'listed_on' => $date->toDateString()]);
            $competition = $this->competition($sport, $path, $header, $name);

            foreach ($items as $row) {
                $this->event($competition, $row, $date);
            }
        }
    }

    /**
     * Адрес турнира из реестра по заголовку списка.
     *
     * @return array{0: string, 1: string}|null [адрес, название турнира]
     */
    private function resolve(Sport $sport, string $header): ?array
    {
        foreach ($this->paths($sport, $header) as $path => $name) {
            if (Page::query()->where('path', $path)->whereNot('origin', 'probe')->exists()) {
                return [$path, $name];
            }
        }

        return null;
    }

    /**
     * Адреса, которые может иметь турнир заголовка: полное название и без
     * подписи стадии/места («Champions League - Preliminary», «Beijing (China), hard»).
     *
     * @return array<string, string> адрес → название турнира
     */
    private function paths(Sport $sport, string $header): array
    {
        if (! str_contains($header, ': ')) {
            return [];
        }
        [$prefix, $name] = explode(': ', $header, 2);
        $slug = (string) config("sports.catalog.sports.{$sport->key}.flashscore");
        $category = Names::slug($prefix);

        $paths = [];
        foreach ([$name, explode(' - ', $name)[0], explode(' (', $name)[0], explode(',', $name)[0]] as $candidate) {
            $path = "/{$slug}/{$category}/".Names::slug($candidate).'/';
            if (trim($candidate) !== '' && PageFetcher::allowed($path)) {
                $paths[$path] ??= trim($candidate);
            }
        }

        return $paths;
    }

    /**
     * Турнир по адресу: уже известный (по адресу его страницы) или
     * предварительный — до загрузки страницы турнира.
     */
    private function competition(Sport $sport, string $path, string $header, string $name): Competition
    {
        $byUrl = CatalogDatabase::connection()->table('catalog_competition_sources')
            ->where('provider_id', $this->provider->id)
            ->where('url', rtrim((string) config('sports.catalog.flashscore.hosts.en'), '/').$path)
            ->value('competition_id');
        $byPath = SourceIds::find('competition', $this->provider->id, 'path', $path);
        $known = $byUrl ?? $byPath;
        if ($known !== null) {
            return Competition::query()->whereKey($known)->firstOrFail();
        }

        [, $categoryKey, $slug] = array_values(array_filter(explode('/', $path)));
        $category = Category::query()->where('sport_id', $sport->id)->where('key', $categoryKey)->first()
            ?? $this->category($sport, $categoryKey, explode(': ', $header, 2)[0]);

        $competition = $this->writer->competition($sport, $category, [
            'name' => $name,
            'slug' => $slug,
            'participant_kind' => $sport->family === 'team' && $category->kind === 'country' ? 'club' : ($sport->family === 'team' ? 'unknown' : 'individual'),
        ]);
        SourceIds::link('competition', $this->provider->id, 'path', $path, $competition->id);
        $this->writer->name($competition, 'en', $name, provider: $this->provider);

        return $competition;
    }

    /** Раздел, которого ещё нет: страна/регион по адресу, название — из заголовка списка. */
    private function category(Sport $sport, string $key, string $label): Category
    {
        $kind = PageImporter::kindFor($sport, $key);
        $name = mb_convert_case(mb_strtolower($label), MB_CASE_TITLE);
        $country = null;
        $region = null;
        if ($kind === 'region') {
            $region = $this->writer->region($key, PageImporter::REGIONS[$key][0], PageImporter::REGIONS[$key][1]);
            $name = PageImporter::REGIONS[$key][0];
        } elseif ($kind === 'country') {
            $iso = NationalTeams::parse($name)['code'] ?? null;
            $country = Country::query()->where('code', $key)->first() ?? $this->writer->country($key, $name, [
                'kind' => $iso !== null && str_starts_with($iso, 'gb-') ? 'sporting_territory' : 'country',
                'iso2' => $iso !== null && strlen($iso) === 2 ? strtoupper($iso) : null,
                'flag_url' => $iso !== null ? "https://flagcdn.com/w80/{$iso}.png" : null,
            ]);
        }

        return $this->writer->category($sport, $kind, $key, $name, $country, $region);
    }

    /**
     * @param  array{header: string, id: string, time: string|null, state: string, label: string|null, home: string, away: string, score: array{0: int, 1: int}|null, walkover: bool}  $row
     */
    private function event(Competition $competition, array $row, CarbonImmutable $date): void
    {
        $status = match (true) {
            $row['label'] !== null && isset(self::LABELS[mb_strtolower($row['label'])]) => self::LABELS[mb_strtolower($row['label'])],
            $row['walkover'] => 'finished',
            $row['state'] === 'sched' => 'scheduled',
            $row['state'] === 'live' => 'live',
            $row['state'] === 'fin' => 'finished',
            default => 'unknown',
        };
        $attributes = [
            'status' => $status,
            'raw_status' => 'lite:'.$row['state'].($row['label'] !== null ? ':'.$row['label'] : ''),
        ];

        $existing = SourceIds::find('event', $this->provider->id, 'event', $row['id']);
        if ($existing !== null) {
            $event = Event::query()->with('competition')->findOrFail($existing);
            $this->writer->event($event->competition, $attributes, [$this->provider, 'event', $row['id']]);
            $this->updated++;

            return;
        }

        // Прошедший матч расписанию не нужен.
        if (in_array($status, ['finished', 'abandoned'], true)) {
            return;
        }
        // Новый матч без времени (идёт, время скрыто прогрессом) — не выдумываем начало.
        if ($row['time'] === null) {
            $this->withoutKickoff++;

            return;
        }

        $start = CarbonImmutable::parse("{$date->toDateString()} {$row['time']}", self::TIMEZONE)->utc();
        $event = $this->writer->event($competition, $attributes + [
            'kind' => $competition->sport->family === 'bout' ? 'bout' : 'match',
            'date' => $start->setTimezone(config('sports.timezone'))->toDateString(),
            'starts_at' => $start,
            'time_precision' => 'exact',
            'source_timezone' => self::TIMEZONE,
        ], [$this->provider, 'event', $row['id']]);
        $this->writer->eventEntries($event, [
            // «unlisted» — имя из дневного списка, без профиля: у списка нет id участников.
            ['side' => 1, 'entry' => null, 'label' => $row['home'], 'entry_status' => 'unlisted'],
            ['side' => 2, 'entry' => null, 'label' => $row['away'], 'entry_status' => 'unlisted'],
        ]);
        $this->created++;
    }

    private function html(string $section, int $offset, CarbonImmutable $date): string
    {
        $key = "catalog:lite:{$section}:{$date->toDateString()}";
        // Дальние дни меняются редко — их список живёт в кэше в 12 раз дольше.
        $minutes = $offset >= 2 ? $this->cacheMinutes * 12 : $this->cacheMinutes;
        $cached = $minutes > 0 ? Cache::get($key) : null;
        if (is_string($cached)) {
            $this->cached++;

            return $cached;
        }

        if ($this->lastRequestAt !== null && $this->pauseSeconds > 0) {
            $wait = $this->pauseSeconds - (microtime(true) - $this->lastRequestAt);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }
        $this->lastRequestAt = microtime(true);
        $this->fetched++;

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(20)->withoutRedirecting()
            ->get(self::HOST.($section === '' ? '/' : "/{$section}/"), ['d' => $offset]);
        if ($response->status() === 429) {
            throw new RateLimited('Rate limited by the day lists');
        }
        if (! $response->successful()) {
            throw new InvalidPage("HTTP {$response->status()}");
        }
        $html = $response->body();
        // В кэш — только разобранная страница.
        $this->parser->parse($html, $offset, $date);
        if ($minutes > 0) {
            Cache::put($key, $html, now()->addMinutes($minutes));
        }

        return $html;
    }
}
