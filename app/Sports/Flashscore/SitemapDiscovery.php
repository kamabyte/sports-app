<?php

namespace App\Sports\Flashscore;

use App\Sports\Catalog\CatalogWriter;
use App\Sports\CatalogDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Все турниры видов спорта каталога — из публичной карты сайта
 * (robots.txt: Sitemap: …/sitemap.xml → …/sitemap/core.xml). Два запроса; в
 * реестр попадают только адреса страниц турниров /вид/раздел/турнир/.
 */
final class SitemapDiscovery
{
    private const string USER_AGENT = 'sports-app/1.0 (personal sports catalog)';

    /** Служебные разделы в адресах вида /sport/odds/…, /golf/calendar/…, /darts/rankings/…. */
    private const array NOT_CATEGORIES = ['odds', 'rankings', 'calendar', 'standings', 'draw', 'live', 'results', 'fixtures'];

    public function __construct(
        private readonly string $host,
        private readonly float $pauseSeconds = 3,
        private readonly CatalogWriter $writer = new CatalogWriter,
    ) {}

    /**
     * Обновить реестр. Возвращает число адресов по видам спорта.
     *
     * @return array<string, int>
     */
    public function discover(): array
    {
        $index = $this->get('/sitemap.xml');
        if (! preg_match('~<loc>(https://www\.flashscore\.com/sitemap/core\.xml)</loc>~', $index, $match)) {
            throw new InvalidPage('Sitemap index has no core.xml');
        }
        usleep((int) ($this->pauseSeconds * 1_000_000));
        $core = $this->get('/sitemap/core.xml');

        preg_match_all('~<loc>https://www\.flashscore\.com(/[a-z0-9-]+/[a-z0-9-]+/[a-z0-9-]+/)</loc>~', $core, $matches);
        $slugs = self::slugs();
        $provider = $this->writer->provider('flashscore', 'Flashscore', 'https://www.flashscore.com');
        $this->writer->syncSports();
        $sportIds = CatalogDatabase::connection()->table('catalog_sports')->pluck('id', 'key');

        $rows = [];
        $counts = [];
        $now = CarbonImmutable::now();
        foreach (array_unique($matches[1]) as $path) {
            [$slug, $category] = array_values(array_filter(explode('/', $path)));
            $sport = $slugs[$slug] ?? null;
            if ($sport === null || in_array($category, self::NOT_CATEGORIES, true) || ! PageFetcher::allowed($path)) {
                continue;
            }
            $rows[] = ['provider_id' => $provider->id, 'sport_id' => $sportIds[$sport], 'path' => $path, 'origin' => 'sitemap', 'created_at' => $now, 'updated_at' => $now];
            $counts[$sport] = ($counts[$sport] ?? 0) + 1;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            CatalogDatabase::connection()->table('catalog_pages')->insertOrIgnore($chunk);
        }
        ksort($counts);

        return $counts;
    }

    /**
     * Раздел Flashscore → вид спорта каталога.
     *
     * @return array<string, string>
     */
    public static function slugs(): array
    {
        $slugs = [];
        foreach (config('sports.catalog.sports') as $key => $sport) {
            $sources = (array) $sport['flashscore'];
            foreach (is_string(array_key_first($sources)) ? array_keys($sources) : $sources as $slug) {
                $slugs[(string) $slug] = (string) $key;
            }
        }

        return $slugs;
    }

    private function get(string $path): string
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->withoutRedirecting()->get(rtrim($this->host, '/').$path);
        if ($response->status() === 429) {
            throw new RateLimited('Rate limited while reading the sitemap');
        }
        if (! $response->successful() || ! str_contains($response->body(), '<loc>')) {
            throw new InvalidPage("Sitemap {$path}: HTTP {$response->status()}, not a sitemap");
        }

        return $response->body();
    }
}
