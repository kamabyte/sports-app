<?php

namespace App\Guide\Fixtures;

use App\Models\Fixture;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * Flashscore lite (www.flashscore.mobi): обычные HTML-страницы видов спорта
 * по дням, без входа и подписанных запросов. Нужен для того, чего нет у
 * бесплатных API: гандбол, волейбол, регби, футзал, хоккей с мячом, водное поло.
 *
 * Условия сайта запрещают сбор данных без согласия — поэтому источник
 * выключен по умолчанию (FLASHSCORE_ENABLED), включает его владелец.
 * Страницы матчей (/match/) закрыты robots.txt — их не запрашиваем.
 *
 * Что проверено (docs/flashscore-html-source-plan.md):
 * - ?d=-1…7 — вчера, сегодня и неделя вперёд от «сегодня» сайта; заголовок
 *   страницы называет день («Today», «13.10.2026») — сверяем;
 * - время — по Европе/Праге (CEST: 27 матчей совпали с LiveScore и ESPN);
 * - строка: <span>20:45</span>Kielce - SC Magdeburg <a href="/match/…" class="sched">;
 * - пустой день — «No match found.»; страница без расписания — ошибка, а не пустой день.
 */
final class FlashscoreSource implements FixtureSource, ReportsDiagnostics
{
    private const string URL = 'https://www.flashscore.mobi';

    private const string USER_AGENT = 'sports-app/1.0 (personal schedule importer)';

    private const int TIMEOUT_SECONDS = 20;

    /** Повтор только при обрыве соединения, не при ответе сервера. */
    private const int RETRIES = 2;

    private const int RETRY_DELAY_MS = 3000;

    /** Дни, которые отдаёт сайт: ?d=8 — уже не расписание. */
    private const int FIRST_OFFSET = -1;

    private const int LAST_OFFSET = 7;

    /** После 429 не ходим на сайт до конца Retry-After (или час). */
    private const string BLOCKED_KEY = 'flashscore:blocked-until';

    private const int DEFAULT_RETRY_AFTER_SECONDS = 3600;

    private const array STATES = ['sched' => Fixture::UPCOMING, 'live' => Fixture::LIVE, 'fin' => Fixture::FINISHED];

    /** Пометки в строке матча; видели Postponed и Abandoned. */
    private const array CANCELLED_LABELS = ['postponed', 'abandoned', 'cancelled', 'canceled', 'awarded', 'interrupted'];

    /** Метки дней в заголовке «Handball » Today » All Games». */
    private const array DAY_LABELS = [-1 => 'Yesterday', 0 => 'Today', 1 => 'Tomorrow'];

    /** @var array<string, list<FixtureData>> страница (вид:день) → матчи, на всю загрузку */
    private array $pages = [];

    /** @var array<string, int> почему пропущены строки → сколько */
    private array $skipped = [];

    /** @var list<string> */
    private array $failures = [];

    private int $fetched = 0;

    private int $cached = 0;

    /**
     * @param  array<string, string>  $sports  наш вид спорта → раздел сайта («handball», «rugby-union»)
     */
    public function __construct(
        private readonly array $sports,
        private readonly string $timezone = 'Europe/Prague',
        private readonly int $pauseSeconds = 2,
        /** Страницы дальше завтрашнего дня меняются редко — храним столько часов. */
        private readonly int $cacheHours = 6,
    ) {}

    public function key(): string
    {
        return 'flashscore';
    }

    public function fixtures(CarbonImmutable $day): array
    {
        $today = CarbonImmutable::now($this->timezone)->startOfDay();
        $fixtures = [];
        $failed = [];
        $loaded = 0;

        // Сутки UTC — это два дня сайта (сегодня с 01:00/02:00 и завтра до 01:00/02:00).
        // Каждая страница — сама по себе: сбой завтрашней не отменяет сегодняшнюю.
        foreach ($this->sports as $sport => $section) {
            foreach ($this->localDates($day) as $date) {
                $offset = (int) round($today->diffInDays($date, false));
                if ($offset < self::FIRST_OFFSET || $offset > self::LAST_OFFSET) {
                    continue;
                }

                try {
                    $page = $this->page($sport, $section, $offset, $date);
                } catch (Throwable $exception) {
                    report($exception);
                    // Без тела ответа: у ошибок HTTP-клиента оно идёт следующими строками.
                    $failed[] = "{$sport} {$date->toDateString()}: ".rtrim((string) strtok($exception->getMessage(), "\n"), ': ');

                    continue;
                }

                $loaded++;
                foreach ($page as $fixture) {
                    if ($fixture->startsAt->isSameDay($day)) {
                        $fixtures[] = $fixture;
                    }
                }
            }
        }

        // Не загрузилась ни одна страница — день не загрузился; иначе отдаём то, что есть.
        if ($failed !== [] && $loaded === 0) {
            throw new RuntimeException(implode('; ', $failed));
        }

        foreach ($failed as $failure) {
            $this->failures[] = "{$day->toDateString()} {$failure}";
        }

        return $fixtures;
    }

    public function diagnostics(): array
    {
        $lines = $this->failures;

        if ($this->skipped !== []) {
            $reasons = collect($this->skipped)->map(fn (int $count, string $reason) => "{$reason}: {$count}")->implode(', ');
            $lines[] = "skipped rows — {$reasons}";
        }

        if ($lines !== []) {
            $lines[] = "pages: {$this->fetched} fetched, {$this->cached} from cache";
        }

        return $lines;
    }

    /**
     * @return list<CarbonImmutable> дни сайта, на которые приходятся сутки UTC
     */
    private function localDates(CarbonImmutable $day): array
    {
        $first = $day->utc()->startOfDay()->setTimezone($this->timezone)->startOfDay();
        $last = $day->utc()->endOfDay()->setTimezone($this->timezone)->startOfDay();

        return $first->eq($last) ? [$first] : [$first, $last];
    }

    /**
     * Матчи страницы одного вида спорта за день сайта — один раз за загрузку.
     *
     * @return list<FixtureData>
     */
    private function page(string $sport, string $section, int $offset, CarbonImmutable $date): array
    {
        $page = "{$sport}:{$date->toDateString()}";
        if (isset($this->pages[$page])) {
            return $this->pages[$page];
        }

        // Сегодня и завтра — всегда свежие (счёт, переносы); дальше — из кэша.
        $key = "flashscore:{$section}:{$date->toDateString()}";
        $cacheable = $offset > 1 && $this->cacheHours > 0;
        $cached = $cacheable ? Cache::get($key) : null;
        $html = is_string($cached) ? $cached : $this->fetch($section, $offset);

        try {
            $fixtures = $this->parse($sport, $html, $offset, $date);
        } catch (Throwable $exception) {
            // Неразобранная страница в кэше не остаётся: следующая загрузка спросит сайт заново.
            if ($cacheable) {
                Cache::forget($key);
            }

            throw $exception;
        }

        // В кэш — только страницу, разобранную целиком.
        if (is_string($cached)) {
            $this->cached++;
        } elseif ($cacheable) {
            Cache::put($key, $html, now()->addHours($this->cacheHours));
        }

        return $this->pages[$page] = $fixtures;
    }

    private function fetch(string $section, int $offset): string
    {
        $blockedUntil = Cache::get(self::BLOCKED_KEY);
        if (is_int($blockedUntil) && $blockedUntil > time()) {
            throw new RuntimeException('rate limited, waiting until '.CarbonImmutable::createFromTimestamp($blockedUntil)->toIso8601String());
        }

        if ($this->fetched > 0 && $this->pauseSeconds > 0) {
            Sleep::for($this->pauseSeconds)->seconds();
        }
        $this->fetched++;

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withUserAgent(self::USER_AGENT)
            ->retry(self::RETRIES, self::RETRY_DELAY_MS, fn (Throwable $exception) => $exception instanceof ConnectionException, throw: false)
            ->get(self::URL."/{$section}/", ['d' => $offset]);

        if ($response->status() === 429) {
            $wait = is_numeric($response->header('Retry-After')) ? (int) $response->header('Retry-After') : self::DEFAULT_RETRY_AFTER_SECONDS;
            Cache::put(self::BLOCKED_KEY, time() + $wait, $wait);

            throw new RuntimeException("rate limited (429), retry after {$wait} s");
        }

        return $response->throw()->body();
    }

    /**
     * @return list<FixtureData>
     */
    private function parse(string $sport, string $html, int $offset, CarbonImmutable $date): array
    {
        $fixtures = [];
        $competition = null;
        $row = null;
        $rows = 0;
        $empty = false;

        foreach ($this->scoreData($html, $offset, $date)->childNodes as $node) {
            if ($node instanceof DOMText) {
                if ($row !== null) {
                    $row['teams'] .= $node->textContent;
                }

                continue;
            }

            if (! $node instanceof DOMElement) {
                continue;
            }

            switch ($node->tagName) {
                case 'h4':
                    // «EUROPE: Champions League - Preliminary» и ссылка на таблицу — без неё.
                    $competition = $this->ownText($node) ?: null;
                    $row = null;
                    break;
                case 'span':
                    $status = null;
                    foreach ($node->getElementsByTagName('span') as $label) {
                        $status = $this->clean($label->textContent);
                    }
                    $row = ['time' => $this->ownText($node), 'status' => $status, 'teams' => ''];
                    break;
                case 'a':
                    if ($row !== null && preg_match('~^/match/([A-Za-z0-9]{6,12})/(\?[^"]*)?$~', $node->getAttribute('href'), $match)) {
                        $rows++;
                        $fixture = $this->fixture($sport, $competition, $row, $match[1], $node, $date);
                        if ($fixture !== null) {
                            $fixtures[] = $fixture;
                        }
                    }
                    $row = null;
                    break;
                case 'br':
                    $row = null;
                    break;
                case 'div':
                    $empty = $empty || str_contains($node->textContent, 'No match found');
                    break;
            }
        }

        if ($rows === 0 && ! $empty) {
            throw new UnexpectedValueException('schedule has neither matches nor the «No match found» marker');
        }

        return $fixtures;
    }

    /**
     * Блок расписания и проверка, что страница — за нужный день: вместо
     * расписания бывает проверка «вы не робот», ошибка или другой макет.
     */
    private function scoreData(string $html, int $offset, CarbonImmutable $date): DOMElement
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($document);

        $data = $this->first($xpath, '//div[@id="score-data"]');
        if ($data === null) {
            throw new UnexpectedValueException('no schedule on the page (challenge, error or changed layout)');
        }

        // «Handball » Today » All Games» — средняя часть называет день.
        $heading = $this->clean((string) $this->first($xpath, '//h2')?->textContent);
        $shown = trim(explode('»', $heading)[1] ?? '');
        $expected = self::DAY_LABELS[$offset] ?? $date->format('d.m.Y');
        if ($shown !== $expected) {
            throw new UnexpectedValueException("page shows «{$shown}», expected «{$expected}»");
        }

        return $data;
    }

    /**
     * @param  array{time: string, status: ?string, teams: string}  $row
     */
    private function fixture(string $sport, ?string $competition, array $row, string $id, DOMElement $link, CarbonImmutable $date): ?FixtureData
    {
        $externalId = "{$sport}:{$id}";
        $teams = $this->clean($row['teams']);

        // «Guinea W - Cameroon W WO» — неявка: матча не будет.
        $walkover = (bool) preg_match('/\sWO$/u', $teams);
        $teams = (string) preg_replace('/\sWO$/u', '', $teams);

        $sides = explode(' - ', $teams);
        if (count($sides) !== 2 || trim($sides[0]) === '' || trim($sides[1]) === '') {
            return $this->skip($sport, 'opponents not split by « - »');
        }

        $state = $link->getAttribute('class');
        $status = match (true) {
            $row['status'] !== null => in_array(mb_strtolower($row['status']), self::CANCELLED_LABELS, true) ? Fixture::CANCELLED : null,
            $walkover => Fixture::CANCELLED,
            default => self::STATES[$state] ?? null,
        };
        if ($status === null) {
            return $this->skip($sport, 'unsupported status «'.($row['status'] ?? $state).'»');
        }

        // Вместо времени у идущего матча — минута («77'»): берём уже известное начало.
        $startsAt = preg_match('/^\d{2}:\d{2}$/', $row['time'])
            ? CarbonImmutable::parse("{$date->toDateString()} {$row['time']}", $this->timezone)->utc()
            : $this->knownKickoff($externalId);
        if ($startsAt === null) {
            return $this->skip($sport, 'live without a known kickoff');
        }

        $score = preg_match('/^(\d+)-(\d+)$/', str_replace(' ', '', $this->clean($link->textContent)), $points) ? $points : null;

        return new FixtureData(
            externalId: $externalId,
            sport: $sport,
            home: trim($sides[0]),
            away: trim($sides[1]),
            startsAt: $startsAt,
            status: $status,
            competition: $competition,
            homeScore: $score !== null ? (int) $score[1] : null,
            awayScore: $score !== null ? (int) $score[2] : null,
        );
    }

    private function first(DOMXPath $xpath, string $query): ?DOMElement
    {
        $nodes = $xpath->query($query);
        $node = $nodes !== false ? $nodes->item(0) : null;

        return $node instanceof DOMElement ? $node : null;
    }

    private function knownKickoff(string $externalId): ?CarbonImmutable
    {
        $startsAt = Fixture::query()->where('source', $this->key())->where('external_id', $externalId)->value('starts_at');

        return $startsAt !== null ? CarbonImmutable::parse($startsAt, 'UTC') : null;
    }

    private function skip(string $sport, string $reason): null
    {
        $key = "{$sport} {$reason}";
        $this->skipped[$key] = ($this->skipped[$key] ?? 0) + 1;

        return null;
    }

    /**
     * Текст самого элемента, без вложенных: время без пометки «Postponed», турнир без ссылки на таблицу.
     */
    private function ownText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->textContent;
            }
        }

        return $this->clean($text);
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }
}
