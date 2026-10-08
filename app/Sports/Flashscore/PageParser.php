<?php

namespace App\Sports\Flashscore;

/**
 * Разбор обычной публичной страницы турнира или команды Flashscore. Данные
 * расписания лежат в самой странице текстом (cjs.initialFeeds): записи
 * разделены «¬~», поля — «¬», ключ и значение — «÷». Скрипты страницы не
 * выполняются, подписанные запросы не делаются — читается только текст.
 *
 * Страница без этих данных и без заголовка — сбой (проверка, другая
 * разметка), а не пустое расписание.
 */
final class PageParser
{
    public const string VERSION = 'flashscore-page/1';

    /** Полные списки; summary-* — их подмножества. */
    private const array FEEDS = ['fixtures', 'results'];

    public function parse(string $html): ParsedPage
    {
        $hasFeeds = str_contains($html, 'cjs.initialFeeds');
        $name = $this->text($html, '~class="heading__name"[^>]*>([^<]*)<~');
        if (! $hasFeeds && $name === null) {
            throw new InvalidPage('No schedule data and no heading: not a competition or team page');
        }

        $headers = [];
        $events = [];
        $sportId = null;
        $seen = [];
        foreach (self::FEEDS as $feed) {
            $data = $this->feed($html, $feed);
            if ($data === null) {
                continue;
            }

            $current = null;
            foreach (explode('¬~', $data) as $record) {
                $fields = $this->fields($record);
                if (isset($fields['SA'])) {
                    $sportId ??= $fields['SA'];
                }
                if (isset($fields['ZA'])) {
                    $headers[] = $fields;
                    $current = count($headers) - 1;
                    $events[$current] = [];
                } elseif (isset($fields['AA']) && $current !== null && ! isset($seen[$fields['AA']])) {
                    $seen[$fields['AA']] = true;
                    $events[$current][] = $fields;
                }
            }
        }

        $lang = $this->text($html, '~<html[^>]*\blang="([A-Za-z-]+)"~');
        $editionLabel = $this->text($html, '~class="heading__info"[^>]*>([^<]*)<~');
        $tournamentId = $this->text($html, '~tournamentId:\s*"([A-Za-z0-9]+)"~');
        // Не у всех страниц турниров есть leaguePageHeaderData (NBA): если у страницы
        // есть подпись сезона и в данных ровно один id сезона — это сезон страницы.
        $editions = array_values(array_unique(array_filter(array_map(fn (array $header) => $header['ZE'] ?? null, $headers), fn (?string $id) => $id !== null && $id !== '' && $id !== '0')));
        if ($tournamentId === null && $editionLabel !== null && count($editions) === 1) {
            $tournamentId = $editions[0];
        }

        return new ParsedPage(
            locale: $lang !== null ? strtolower(explode('-', $lang)[0]) : null,
            name: $name,
            editionLabel: $editionLabel,
            archive: (bool) preg_match('~"archive":true~', $html),
            templateId: $this->text($html, '~tournamentTemplateId:\s*"([A-Za-z0-9]+)"~'),
            tournamentId: $tournamentId,
            stageId: $this->text($html, '~tournamentStageId:\s*"([A-Za-z0-9]+)"~'),
            sportId: $sportId,
            blocks: $this->mergeBlocks(array_map(fn (int $index) => ['header' => $headers[$index], 'events' => $events[$index] ?? []], array_keys($headers))),
        );
    }

    /** Текст фида как данные: содержимое `…` после «data:». */
    private function feed(string $html, string $name): ?string
    {
        $pattern = '~initialFeeds\[[\'"]'.preg_quote($name, '~').'[\'"]\]\s*=\s*\{\s*data:\s*`([^`]*)`~u';

        return preg_match($pattern, $html, $match) ? $match[1] : null;
    }

    /**
     * Поля записи «КЛЮЧ÷значение¬…». Повтор ключа (бывает у AS/AZ) — первое значение.
     *
     * @return array<string, string>
     */
    private function fields(string $record): array
    {
        $fields = [];
        foreach (explode('¬', $record) as $pair) {
            $position = strpos($pair, '÷');
            if ($position === false) {
                continue;
            }
            $key = substr($pair, 0, $position);
            if ($key !== '' && ! array_key_exists($key, $fields)) {
                $fields[$key] = html_entity_decode(substr($pair, $position + strlen('÷')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $fields;
    }

    /**
     * Один и тот же турнир/стадия встречается и в календаре, и в результатах — один блок.
     *
     * @param  list<array{header: array<string, string>, events: list<array<string, string>>}>  $blocks
     * @return list<array{header: array<string, string>, events: list<array<string, string>>}>
     */
    private function mergeBlocks(array $blocks): array
    {
        $merged = [];
        foreach ($blocks as $block) {
            $key = ($block['header']['ZEE'] ?? '').'|'.($block['header']['ZC'] ?? '').'|'.($block['header']['ZA'] ?? '');
            if (isset($merged[$key])) {
                $merged[$key]['events'] = [...$merged[$key]['events'], ...$block['events']];
            } else {
                $merged[$key] = $block;
            }
        }

        return array_values($merged);
    }

    private function text(string $html, string $pattern): ?string
    {
        if (! preg_match($pattern, $html, $match)) {
            return null;
        }
        $value = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value === '' ? null : $value;
    }
}
