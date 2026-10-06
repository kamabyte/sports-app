<?php

namespace App\Guide\Listings;

use App\Models\Channel;
use Illuminate\Support\Collection;
use Locale;

/**
 * Какой канал телегида — наш канал. Сначала по tvg-id из плейлиста
 * («Sport1.cz@SD» → «Sport1.cz», «2003» у EPG One), потом по названию и
 * стране: «Sport1» с tvg-id «.cz» — чешский Sport1, а не венгерский. По
 * названию берём только канал с настоящей программой, а не одной передачей
 * на весь день.
 */
final class ChannelMatcher
{
    /** Слова, которыми названия одного канала отличаются в разных списках. */
    private const array NOISE = ['hd', 'sd', 'uhd', 'fhd', '4k', 'hevc', 'live', 'east', 'west', 'channel', 'tv', 'network'];

    /** Что бывает после @ в tvg-id у того же канала: качество и поток, а не версия. */
    private const array FEEDS = ['sd', 'hd', 'fhd', 'uhd', '4k', 'internet', 'east', 'west', 'pacific'];

    /** Передач за двое суток и разных названий среди них — у совпадения по названию. */
    private const int MIN_PROGRAMMES = 4;

    private const int MIN_TITLES = 2;

    /**
     * Каналы телегидов, похожие на наши.
     *
     * @param  Collection<int, Channel>  $channels
     * @param  array<string, array<string, list<string>>>  $guides  источник → id канала телегида → названия
     * @param  list<string>  $anyCountry  источники каналов без страны
     * @return array<int, list<array{source: string, channel: string, exact: bool, country: bool}>> по id нашего канала
     */
    public function candidates(Collection $channels, array $guides, array $anyCountry): array
    {
        [$ids, $names] = $this->index($guides);
        $candidates = [];

        foreach ($channels as $channel) {
            $tvgId = $this->tvgId($channel->tvg_id);
            $country = $this->country($tvgId);
            $found = [];

            foreach ($ids[mb_strtolower((string) $tvgId)] ?? [] as $entry) {
                $found["{$entry['source']}:{$entry['channel']}"] = [...$entry, 'exact' => true, 'country' => true];
            }

            // «FIFAPlus.uk@Spain» — своя версия канала: по общему «FIFAPlus» её не искать.
            $variant = preg_match('/@(.+)$/', (string) $channel->tvg_id, $match) && ! in_array(strtolower($match[1]), self::FEEDS, true);
            $keys = array_filter(array_unique([$this->key($channel->name), $country !== null && ! $variant ? $this->key($this->base((string) $tvgId)) : '']));
            foreach ($keys as $key) {
                foreach ($names[$key] ?? [] as $entry) {
                    // Страна канала телегида неизвестна — не повод отказать, но своя страна лучше.
                    $sameCountry = $country === null || $this->sameCountry($country, $entry['countries']);
                    if (! $sameCountry && $entry['countries'] !== [] && ! in_array($entry['source'], $anyCountry, true)) {
                        continue;
                    }

                    $found["{$entry['source']}:{$entry['channel']}"] ??= [...$entry, 'exact' => false, 'country' => $sameCountry];
                }
            }

            $candidates[$channel->id] = array_values(array_map(
                fn (array $entry) => ['source' => $entry['source'], 'channel' => $entry['channel'], 'exact' => $entry['exact'], 'country' => $entry['country']],
                $found,
            ));
        }

        return $candidates;
    }

    /**
     * Лучший кандидат: точный tvg-id, потом своя страна, потом больше разных
     * передач, потом порядок источников.
     *
     * @param  list<array{source: string, channel: string, exact: bool, country: bool}>  $candidates
     * @param  array<string, array<string, array{programmes: int, titles: int}>>  $stats  источник → канал → передачи за двое суток
     * @param  list<string>  $order  источники по приоритету
     * @return array{source: string, channel: string}|null
     */
    public function choose(array $candidates, array $stats, array $order): ?array
    {
        $best = null;
        $bestRank = null;

        foreach ($candidates as $candidate) {
            $stat = $stats[$candidate['source']][$candidate['channel']] ?? ['programmes' => 0, 'titles' => 0];
            $usable = $candidate['exact']
                ? $stat['programmes'] > 0
                : $stat['programmes'] >= self::MIN_PROGRAMMES && $stat['titles'] >= self::MIN_TITLES;

            if (! $usable) {
                continue;
            }

            $priority = array_search($candidate['source'], $order, true);
            $rank = [(int) $candidate['exact'], (int) $candidate['country'], $stat['titles'], $stat['programmes'], -($priority === false ? PHP_INT_MAX : $priority)];

            if ($bestRank === null || $rank > $bestRank) {
                [$best, $bestRank] = [['source' => $candidate['source'], 'channel' => $candidate['channel']], $rank];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $guides
     * @return array{0: array<string, list<array{source: string, channel: string, countries: list<string>}>>, 1: array<string, list<array{source: string, channel: string, countries: list<string>}>>}
     */
    private function index(array $guides): array
    {
        $ids = [];
        $names = [];

        foreach ($guides as $source => $channels) {
            foreach ($channels as $id => $displayNames) {
                $id = (string) $id;
                // EPG One дописывает страну к названию: «Teledeporte HD ES», «SPORT 4 HD IL».
                $countries = [];
                $withoutCountry = [];
                foreach ($displayNames as $name) {
                    if (preg_match('/^(.+?)\s+([A-Z]{2})$/u', $name, $match) && $this->isCountry($match[2])) {
                        $countries[] = strtolower($match[2]);
                        $withoutCountry[] = $match[1];
                    }
                }

                $keys = array_values(array_filter(array_unique(array_map(
                    fn (string $name) => $this->key($name),
                    [...$displayNames, ...$withoutCountry, $this->base($id)],
                ))));
                $entry = ['source' => $source, 'channel' => $id, 'countries' => array_values(array_unique(array_filter([$this->country($id), ...$countries])))];

                $ids[mb_strtolower($id)][] = $entry;
                foreach ($keys as $key) {
                    $names[$key][] = $entry;
                }
            }
        }

        return [$ids, $names];
    }

    /** «Sport1.cz@SD» → «Sport1.cz»: после @ — вариант потока, а не канал. */
    private function tvgId(?string $tvgId): ?string
    {
        $tvgId = trim((string) preg_replace('/@.*$/', '', (string) $tvgId));

        return $tvgId !== '' ? $tvgId : null;
    }

    /** Страна из id: «Sport1.cz», «Sport1.(hu).cz», «BBC.One.uk2». */
    private function country(?string $id): ?string
    {
        return preg_match('/\.([a-z]{2})\d?$/i', (string) $id, $match) ? strtolower($match[1]) : null;
    }

    /** «Sport1.cz» → «Sport1», «Bally.Sports.Ohio.us» → «Bally Sports Ohio». */
    private function base(string $id): string
    {
        return str_replace('.', ' ', (string) preg_replace('/\.[a-z]{2}\d?$/i', '', $id));
    }

    /**
     * @param  list<string>  $countries
     */
    private function sameCountry(string $country, array $countries): bool
    {
        $uk = ['gb', 'uk'];

        return in_array($country, $countries, true) || (in_array($country, $uk, true) && array_intersect($uk, $countries) !== []);
    }

    /** «IL», «DE» — страна; «HD», «TV» (Тувалу, но нет) — нет. */
    private function isCountry(string $code): bool
    {
        return ! in_array($code, ['HD', 'SD', 'TV', 'FM'], true) && Locale::getDisplayRegion("und_{$code}", 'en') !== $code;
    }

    /** Один ключ у «Sport 1 HD» и «Sport1», «FOX Sports (East)» и «Fox Sports», «Fast&FunBox» и «Fast & Fun Box». */
    private function key(string $name): string
    {
        $name = mb_strtolower((string) preg_replace('/\([^)]*\)/u', ' ', $name));
        $name = str_replace(['+', '&'], [' plus ', ' and '], $name);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_diff($words, self::NOISE));
    }
}
