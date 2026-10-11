<?php

namespace App\Guide\Listings;

use App\Guide\Dispatcharr\XtreamClient;
use App\Guide\SportRegistry;
use App\Models\Channel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Каналы — из Dispatcharr, телегид — свой: XMLTV из sports.guides.sources.
 * Каналы привязываются к телегидам сами (ChannelMatcher), передачи
 * разбираются (TitleParser — по названию, а если там только вид спорта, то
 * с подзаголовком или описанием) и привязываются к событиям (AiringTarget:
 * каталог или прежнее приложение): прямой эфир, одновременный показ, повтор.
 * События создаёт расписание, телегид — нет: передача, не нашедшая события,
 * не показывается.
 */
final readonly class GuideImporter
{
    /** Передача без соперников короче этого — скорее студия или обзор. */
    private const int MIN_TITLED_MINUTES = 40;

    private const int MIN_MATCH_MINUTES = 20;

    /** По этим суткам видно, настоящая ли у канала программа. */
    private const int STATS_HOURS = 48;

    /**
     * @param  list<GuideSource>  $sources
     * @param  array<string, string|null>  $overrides  название канала → «источник:id канала» или null
     * @param  array<string, string>  $categoryLanguages  название категории → язык каналов
     */
    public function __construct(
        private XtreamClient $client,
        private GuideFiles $files,
        private XmltvReader $reader,
        private ChannelMatcher $channelMatcher,
        private TitleParser $parser,
        private AiringTarget $target,
        private array $sources,
        private int $daysAhead,
        private array $overrides = [],
        private array $categoryLanguages = [],
    ) {}

    public function import(): ImportResult
    {
        $now = CarbonImmutable::now('UTC');
        [$paths, $guides, $errors] = $this->loadGuides();
        $channels = $this->importChannels();
        [$programmes, $refreshedChannels] = $this->readProgrammes($channels, $paths, $guides, $now);

        $parsed = $this->parseProgrammes($programmes);
        $windowStart = $now->subDay();
        $windowEnd = $now->addDays($this->daysAhead);
        $airings = $this->target->rebuild($windowStart, $windowEnd, $parsed, $refreshedChannels);

        return new ImportResult(
            channels: $channels->count(),
            withGuide: $channels->whereNotNull('guide_source')->count(),
            programmes: count($parsed),
            airings: $airings,
            events: $this->target->eventsWithAirings($windowStart),
            errors: $errors,
        );
    }

    /**
     * @return Collection<int, Channel>
     */
    private function importChannels(): Collection
    {
        $streams = $this->client->streams();
        if ($streams === []) {
            // Пустой ответ — скорее сбой источника: каналы (и показы) не трогаем.
            throw new RuntimeException('Dispatcharr returned no channels.');
        }

        $categories = collect($this->client->categories())->pluck('category_name', 'category_id');

        // Без tvg-id каналы находятся только по названию — это не повод не загружать.
        try {
            $tvgIds = $this->client->tvgIds();
        } catch (Throwable) {
            $tvgIds = [];
        }

        $ids = [];
        foreach ($streams as $stream) {
            $category = $categories[(string) ($stream['category_id'] ?? '')] ?? null;
            $number = (int) ($stream['num'] ?? 0);

            $channel = Channel::query()->updateOrCreate(['stream_id' => (int) $stream['stream_id']], [
                'number' => $number,
                'name' => (string) $stream['name'],
                'logo' => ($stream['stream_icon'] ?? '') ?: null,
                // Без своего tvg-id Dispatcharr подставляет номер канала — это не id телегида.
                'tvg_id' => isset($tvgIds[$number]) && $tvgIds[$number] !== (string) $number ? $tvgIds[$number] : null,
                'category' => $category,
                'language' => $category !== null ? ($this->categoryLanguages[$category] ?? null) : null,
            ]);
            $ids[] = $channel->id;
        }

        Channel::query()->whereNotIn('id', $ids)->delete();

        return Channel::query()->get();
    }

    /**
     * Файлы телегидов и их каналы. Не загрузился ни один — ошибка: без
     * телегида все показы пропали бы.
     *
     * @return array{0: array<string, string>, 1: array<string, array<string, list<string>>>, 2: list<string>}
     */
    private function loadGuides(): array
    {
        $paths = [];
        $guides = [];
        $errors = [];

        foreach ($this->sources as $source) {
            try {
                $file = $this->files->get($source);
                $paths[$source->key] = $file['path'];
                $guides[$source->key] = $this->reader->channels($file['path']);
                if ($file['error'] !== null) {
                    $errors[] = $file['error'];
                }
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($guides === []) {
            throw new RuntimeException('No guide could be loaded: '.implode('; ', $errors));
        }

        return [$paths, $guides, $errors];
    }

    /**
     * Привязывает каналы к телегидам и возвращает передачи привязанных каналов.
     *
     * @param  Collection<int, Channel>  $channels
     * @param  array<string, string>  $paths
     * @param  array<string, array<string, list<string>>>  $guides
     * @return array{0: list<array{programme: Programme, channel: Channel}>, 1: list<int>}
     */
    private function readProgrammes(Collection $channels, array $paths, array $guides, CarbonImmutable $now): array
    {
        $order = array_map(fn (GuideSource $source) => $source->key, $this->sources);
        $anyCountry = array_values(array_map(
            fn (GuideSource $source) => $source->key,
            array_filter($this->sources, fn (GuideSource $source) => $source->anyCountry),
        ));
        $candidates = $this->overridden($channels, $this->channelMatcher->candidates($channels, $guides, $anyCountry));

        // Передачи всех кандидатов: какой из них настоящий, видно по программе.
        $wanted = [];
        foreach ($candidates as $list) {
            foreach ($list as $candidate) {
                $wanted[$candidate['source']][$candidate['channel']] = true;
            }
        }

        $programmes = [];
        $stats = [];
        foreach ($wanted as $source => $ids) {
            if (! isset($paths[$source])) {
                continue;
            }

            foreach ($this->reader->programmes($paths[$source], $source, $ids, $now->subDay(), $now->addDays($this->daysAhead)) as $programme) {
                $programmes[$source][$programme->channel][] = $programme;
            }

            foreach ($programmes[$source] ?? [] as $id => $list) {
                $upcoming = array_filter($list, fn (Programme $programme) => $programme->endsAt->gt($now) && $programme->startsAt->lt($now->addHours(self::STATS_HOURS)));
                $stats[$source][$id] = [
                    'programmes' => count($upcoming),
                    'titles' => count(array_unique(array_map(fn (Programme $programme) => $programme->title, $upcoming))),
                ];
            }
        }

        $result = [];
        $refreshed = [];
        foreach ($channels as $channel) {
            // A missing source must not erase its last known mapping or broadcasts.
            $source = array_key_exists($channel->name, $this->overrides)
                ? (explode(':', (string) $this->overrides[$channel->name], 2)[0] ?: null)
                : $channel->guide_source;
            if ($source !== null && in_array($source, $order, true) && ! isset($paths[$source])) {
                continue;
            }
            $refreshed[] = $channel->id;
            $choice = $this->channelMatcher->choose($candidates[$channel->id] ?? [], $stats, $order);
            $channel->update(['guide_source' => $choice['source'] ?? null, 'guide_channel' => $choice['channel'] ?? null]);

            foreach ($choice !== null ? $programmes[$choice['source']][$choice['channel']] ?? [] : [] as $programme) {
                $result[] = ['programme' => $programme, 'channel' => $channel];
            }
        }

        return [$result, $refreshed];
    }

    /**
     * Ручная привязка (sports.guides.channels) вместо найденной.
     *
     * @param  Collection<int, Channel>  $channels
     * @param  array<int, list<array{source: string, channel: string, exact: bool, country: bool}>>  $candidates
     * @return array<int, list<array{source: string, channel: string, exact: bool, country: bool}>>
     */
    private function overridden(Collection $channels, array $candidates): array
    {
        foreach ($channels as $channel) {
            if (! array_key_exists($channel->name, $this->overrides)) {
                continue;
            }

            $override = explode(':', (string) $this->overrides[$channel->name], 2);
            $candidates[$channel->id] = count($override) === 2
                ? [['source' => $override[0], 'channel' => $override[1], 'exact' => true, 'country' => true]]
                : [];
        }

        return $candidates;
    }

    /**
     * Спортивные передачи: что удалось разобрать.
     *
     * @param  list<array{programme: Programme, channel: Channel}>  $programmes
     * @return list<array{programme: Programme, channel: Channel, parsed: ParsedTitle}>
     */
    private function parseProgrammes(array $programmes): array
    {
        $parsed = [];

        foreach ($programmes as ['programme' => $programme, 'channel' => $channel]) {
            $title = $this->parse($programme, $channel);
            $minutes = $programme->startsAt->diffInMinutes($programme->endsAt);

            if ($title === null || $minutes < ($title->hasTeams() ? self::MIN_MATCH_MINUTES : self::MIN_TITLED_MINUTES)) {
                continue;
            }

            $parsed[] = ['programme' => $programme, 'channel' => $channel, 'parsed' => $title];
        }

        return $parsed;
    }

    /**
     * По названию; если соперников в нём нет («Lední hokej», «WNBA Basketball»)
     * — вместе с подзаголовком или описанием, где они бывают.
     */
    private function parse(Programme $programme, Channel $channel): ?ParsedTitle
    {
        if ($programme->title === '') {
            return null;
        }

        $hint = SportRegistry::hint($channel->name);
        $parsed = $this->parser->parse($programme->title, $programme->startsAt, $hint);

        if ($parsed?->hasTeams() && $parsed->knowsSport()) {
            return $parsed;
        }

        // Вид спорта или соперники бывают в описании: «Дортмунд – Вердер» + «Футбол. Бундеслига».
        foreach ($programme->details() as $detail) {
            $detailed = $this->parser->parse(rtrim($programme->title, ' .').'. '.$detail, $programme->startsAt, $hint);
            if ($detailed?->hasTeams() && ($detailed->knowsSport() || ! $parsed?->hasTeams())) {
                return $detailed;
            }
        }

        return $parsed;
    }
}
