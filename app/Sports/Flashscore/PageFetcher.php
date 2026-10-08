<?php

namespace App\Sports\Flashscore;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Загрузка публичных страниц Flashscore: только разрешённые хосты и адреса
 * страниц турниров и команд (robots.txt их не запрещает; /standings/,
 * /draw/, /match/ и ленты /x/ не трогаем). Пауза между запросами, один
 * повтор при сбое сервера, 429 — остановка. В кэш попадает только
 * проверенная страница, страница проверки или ошибки — никогда.
 */
final class PageFetcher
{
    private const string USER_AGENT = 'sports-app/1.0 (personal sports catalog)';

    /** Страница турнира (/football/england/premier-league/[fixtures/]) или команды (/team/france/QkGeVG1n/). */
    private const string ALLOWED_PATH = '~^/(?:[a-z0-9-]+/[a-z0-9-]+/[a-z0-9-]+/(?:fixtures/|results/)?|team/[a-z0-9-]+/[A-Za-z0-9]{8}/)$~';

    private const array DISALLOWED = ['/standings/', '/draw/', '/match/', '/x/', '/live/', '/my/', '/finished/', '/scheduled/'];

    private ?float $lastRequestAt = null;

    public int $fetched = 0;

    public int $cached = 0;

    /**
     * @param  array<string, string>  $hosts  язык → https://host
     */
    public function __construct(
        private readonly array $hosts,
        private readonly float $pauseSeconds,
        private readonly int $cacheHours,
        private readonly int $timeoutSeconds,
        private readonly PageParser $parser = new PageParser,
    ) {}

    public static function allowed(string $path): bool
    {
        foreach (self::DISALLOWED as $prefix) {
            if (str_contains($path, $prefix)) {
                return false;
            }
        }

        return (bool) preg_match(self::ALLOWED_PATH, $path);
    }

    public function url(string $locale, string $path): string
    {
        $host = $this->hosts[$locale] ?? throw new InvalidPage("No host configured for locale {$locale}");

        return rtrim($host, '/').$path;
    }

    /**
     * Страница и её разбор; из кэша, если она свежая.
     *
     * @return array{page: ParsedPage, url: string, status: int|null, from_cache: bool, hash: string}
     */
    public function get(string $locale, string $path): array
    {
        if (! self::allowed($path)) {
            throw new InvalidPage("Path {$path} is not an allowed public competition or team page");
        }

        $url = $this->url($locale, $path);
        $cacheFile = 'flashscore/'.sha1($url).'.html';
        $disk = Storage::disk('local');

        if ($this->cacheHours > 0 && $disk->exists($cacheFile) && CarbonImmutable::createFromTimestamp($disk->lastModified($cacheFile))->gt(CarbonImmutable::now()->subHours($this->cacheHours))) {
            $html = (string) $disk->get($cacheFile);
            $this->cached++;

            return ['page' => $this->parser->parse($html), 'url' => $url, 'status' => null, 'from_cache' => true, 'hash' => hash('sha256', $html)];
        }

        [$status, $html] = $this->request($url);
        $page = $this->parser->parse($html);
        $disk->put($cacheFile, $html);

        return ['page' => $page, 'url' => $url, 'status' => $status, 'from_cache' => false, 'hash' => hash('sha256', $html)];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function request(string $url): array
    {
        for ($attempt = 1; ; $attempt++) {
            $this->pace();
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept-Language' => 'en,ru;q=0.8'])
                    ->timeout($this->timeoutSeconds)
                    ->withoutRedirecting()
                    ->get($url);
            } catch (ConnectionException $exception) {
                if ($attempt < 2) {
                    continue;
                }
                throw new InvalidPage("Connection failed: {$exception->getMessage()}");
            }
            $this->fetched++;

            if ($response->status() === 429) {
                throw new RateLimited("Rate limited by {$url}".($response->header('Retry-After') ? ', retry after '.$response->header('Retry-After').' s' : ''));
            }
            if ($response->serverError() && $attempt < 2) {
                continue;
            }
            if ($response->redirect()) {
                throw new InvalidPage("Redirected to {$response->header('Location')}: page moved or blocked");
            }
            if (! $response->successful()) {
                throw new InvalidPage("HTTP {$response->status()}");
            }

            return [$response->status(), $response->body()];
        }
    }

    private function pace(): void
    {
        if ($this->lastRequestAt !== null && $this->pauseSeconds > 0) {
            $wait = $this->pauseSeconds - (microtime(true) - $this->lastRequestAt);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }
        $this->lastRequestAt = microtime(true);
    }
}
