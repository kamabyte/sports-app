<?php

namespace App\Guide\Listings;

use Carbon\CarbonImmutable;
use DOMDocument;
use RuntimeException;
use SimpleXMLElement;
use Throwable;
use XMLReader;

/**
 * Читает XMLTV потоком (EPG One — 700 тысяч передач за пару секунд): .gz и
 * обычный XML одинаково — через compress.zlib://.
 */
final class XmltvReader
{
    /**
     * Каналы телегида: id → названия. Каналы в XMLTV идут перед передачами —
     * читаем до первой передачи.
     *
     * @return array<string, list<string>>
     */
    public function channels(string $path): array
    {
        $reader = $this->open($path);
        $channels = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'programme') {
                break;
            }

            if ($reader->name === 'channel' && ($node = $this->node($reader)) !== null) {
                $names = [];
                foreach ($node->{'display-name'} as $name) {
                    $names[] = trim((string) $name);
                }
                $channels[(string) $node['id']] = array_values(array_filter($names));
            }
        }

        $reader->close();

        return $channels;
    }

    /**
     * Передачи этих каналов, которые идут в окне [from, to].
     *
     * @param  array<string, true>  $channels  id каналов телегида
     * @return list<Programme>
     */
    public function programmes(string $path, string $source, array $channels, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($channels === []) {
            return [];
        }

        $reader = $this->open($path);
        $programmes = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'programme') {
                continue;
            }

            // Чужие каналы пропускаем не разбирая: их в файле — тысячи.
            $channel = (string) $reader->getAttribute('channel');
            if (! isset($channels[$channel])) {
                $reader->next();

                continue;
            }

            $node = $this->node($reader);
            $startsAt = $this->time((string) $node?->attributes()['start']);
            $endsAt = $this->time((string) $node?->attributes()['stop']);

            if ($node === null || $startsAt === null || $endsAt === null || $endsAt->lte($from) || $startsAt->gte($to)) {
                continue;
            }

            $programmes[] = new Programme(
                source: $source,
                channel: $channel,
                title: trim((string) $node->title),
                subTitle: trim((string) $node->{'sub-title'}) ?: null,
                description: trim((string) $node->desc) ?: null,
                startsAt: $startsAt,
                endsAt: $endsAt,
            );
        }

        $reader->close();

        return $programmes;
    }

    private function open(string $path): XMLReader
    {
        libxml_use_internal_errors(true);
        $reader = new XMLReader;

        if (! $reader->open('compress.zlib://'.$path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException("Cannot open the guide {$path}.");
        }

        return $reader;
    }

    private function node(XMLReader $reader): ?SimpleXMLElement
    {
        $element = $reader->expand(new DOMDocument);

        return $element !== false ? simplexml_import_dom($element) : null;
    }

    /** «20261006150000 +0000»; без пояса — UTC. */
    private function time(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        try {
            $time = strlen($value) > 14
                ? CarbonImmutable::createFromFormat('YmdHis O', $value)
                : CarbonImmutable::createFromFormat('YmdHis', $value, 'UTC');
        } catch (Throwable) {
            return null;
        }

        return $time?->utc();
    }
}
