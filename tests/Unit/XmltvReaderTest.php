<?php

use App\Guide\Listings\XmltvReader;
use Carbon\CarbonImmutable;

it('reads a wanted programme immediately after an unwanted programme in compact XML', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xmltv-');
    file_put_contents($path, '<tv><programme channel="other" start="20261011100000 +0000" stop="20261011120000 +0000"><title>Other</title></programme><programme channel="sport" start="20261011100000 +0000" stop="20261011120000 +0000"><title>Football</title></programme></tv>');
    try {
        $programmes = (new XmltvReader)->programmes($path, 'test', ['sport' => true], CarbonImmutable::parse('2026-10-11'), CarbonImmutable::parse('2026-10-12'));
        expect(array_column($programmes, 'title'))->toBe(['Football']);
    } finally {
        unlink($path);
    }
});
