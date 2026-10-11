<?php

use App\Guide\Dispatcharr\XtreamClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// В Dokploy публичный адрес Dispatcharr контейнеру закрыт (Traefik, nas-admin-only):
// API — по внутреннему адресу, ссылки на потоки — по публичному, для браузера.

it('calls the API at the internal address and builds stream links from the public one', function (): void {
    Http::fake([
        'dispatcharr-internal:9191/player_api.php*' => Http::response([['category_id' => 1, 'category_name' => 'Спорт']]),
        'dispatcharr-internal:9191/output/m3u*' => Http::response("#EXTINF:-1 tvg-id=\"Match.ru\" tvg-chno=\"5\",Матч\n"),
    ]);

    $client = new XtreamClient('http://iptv.home.internal', 'tester', 'secret', 'http://dispatcharr-internal:9191');

    expect($client->categories())->toHaveCount(1)
        ->and($client->tvgIds())->toBe([5 => 'Match.ru'])
        ->and($client->streamUrl(5))->toBe('http://iptv.home.internal/live/tester/secret/5?output_format=ts');

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'iptv.home.internal'));
});

it('uses the public address for the API without an internal one', function (): void {
    Http::fake(['iptv.home.internal/player_api.php*' => Http::response([])]);

    expect((new XtreamClient('http://iptv.home.internal', 'tester', 'secret'))->streams())->toBe([]);

    Http::assertSentCount(1);
});
