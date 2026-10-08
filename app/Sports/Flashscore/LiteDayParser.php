<?php

namespace App\Sports\Flashscore;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Дневной список лёгкой версии сайта (www.flashscore.mobi/{вид}/?d=N):
 * заголовок турнира «ENGLAND: Premier League», строки «<span>20:45</span>
 * Home - Away <a href="/match/{id}/" class="sched|live|fin">2-1</a>».
 * Время — по Праге (сверено с UTC-временем страниц турниров). id матча
 * совпадает с id события на страницах турниров (проверено на НХЛ и КХЛ).
 *
 * Без #score-data, без строк и без «No match found» — сбой, а не пустой день.
 * День страницы сверяется с заголовком h2 («… » Today » …»).
 */
final class LiteDayParser
{
    private const array DAY_LABELS = [-1 => 'Yesterday', 0 => 'Today', 1 => 'Tomorrow'];

    /**
     * @return list<array{header: string, id: string, time: string|null, state: string, label: string|null, home: string, away: string, score: array{0: int, 1: int}|null, walkover: bool}>
     */
    public function parse(string $html, int $offset, CarbonImmutable $date): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($document);

        $data = $this->first($xpath, '//div[@id="score-data"]');
        if ($data === null) {
            throw new InvalidPage('No schedule on the page (challenge, error or changed layout)');
        }
        $heading = $this->clean((string) $this->first($xpath, '//h2')?->textContent);
        $shown = trim(explode('»', $heading)[1] ?? '');
        $expected = self::DAY_LABELS[$offset] ?? $date->format('d.m.Y');
        if ($shown !== $expected) {
            throw new InvalidPage("Page shows «{$shown}», expected «{$expected}»");
        }

        $rows = [];
        $header = null;
        $row = null;
        $links = 0;
        $empty = false;
        foreach ($data->childNodes as $node) {
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
                    $header = $this->ownText($node) ?: null;
                    $row = null;
                    break;
                case 'span':
                    $label = null;
                    foreach ($node->getElementsByTagName('span') as $inner) {
                        $label = $this->clean($inner->textContent);
                    }
                    $row = ['time' => $this->ownText($node), 'label' => $label, 'teams' => ''];
                    break;
                case 'a':
                    if ($row !== null && $header !== null && preg_match('~^/match/([A-Za-z0-9]{8})/(\?[^"]*)?$~', $node->getAttribute('href'), $match)) {
                        $links++;
                        $teams = $this->clean($row['teams']);
                        $walkover = (bool) preg_match('/\sWO$/u', $teams);
                        $sides = explode(' - ', (string) preg_replace('/\sWO$/u', '', $teams));
                        if (count($sides) === 2 && trim($sides[0]) !== '' && trim($sides[1]) !== '') {
                            $score = preg_match('/^(\d+)-(\d+)$/', str_replace(' ', '', $this->clean($node->textContent)), $points) ? [(int) $points[1], (int) $points[2]] : null;
                            $rows[] = [
                                'header' => $header,
                                'id' => $match[1],
                                'time' => preg_match('/^\d{2}:\d{2}$/', $row['time']) ? $row['time'] : null,
                                'state' => $node->getAttribute('class'),
                                'label' => $row['label'],
                                'home' => trim($sides[0]),
                                'away' => trim($sides[1]),
                                'score' => $score,
                                'walkover' => $walkover,
                            ];
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

        if ($links === 0 && ! $empty) {
            throw new InvalidPage('Schedule has neither matches nor the «No match found» marker');
        }

        return $rows;
    }

    private function first(DOMXPath $xpath, string $query): ?DOMElement
    {
        $nodes = $xpath->query($query);
        $node = $nodes !== false ? $nodes->item(0) : null;

        return $node instanceof DOMElement ? $node : null;
    }

    /** Текст самого элемента, без вложенных: турнир без ссылки на таблицу, время без пометки. */
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
