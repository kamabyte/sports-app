<?php

namespace App\Guide\Dispatcharr;

use Illuminate\Support\Facades\Http;

/**
 * Xtream Codes API Dispatcharr: каналы, категории, их tvg-id и адреса потоков.
 * Телегид у нас свой (App\Guide\Listings).
 *
 * Два адреса: $url — для браузера (из него ссылки на потоки), $apiUrl — для
 * запросов самого приложения. В контейнере публичный адрес закрыт Traefik
 * (nas-admin-only), и API идёт к Dispatcharr напрямую по сети Docker.
 */
final readonly class XtreamClient
{
    private const int TIMEOUT_SECONDS = 60;

    public function __construct(
        private string $url,
        private string $username,
        private string $password,
        private ?string $apiUrl = null,
    ) {}

    /**
     * @return list<array<string, mixed>> category_id, category_name
     */
    public function categories(): array
    {
        return $this->api('get_live_categories');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function streams(): array
    {
        return $this->api('get_live_streams');
    }

    /**
     * tvg-id каналов по номеру — из плейлиста Dispatcharr (Xtream отдаёт вместо
     * него номер канала). По нему канал находится в телегидах. Плейлист
     * без логина: в нём ничего, кроме названий и ссылок прокси.
     *
     * @return array<int, string> номер канала → tvg-id
     */
    public function tvgIds(): array
    {
        $playlist = Http::timeout(self::TIMEOUT_SECONDS)
            ->get("{$this->apiBase()}/output/m3u", ['tvg_id_source' => 'tvg_id'])
            ->throw()
            ->body();

        preg_match_all('/^#EXTINF:[^\n]*?tvg-id="([^"]*)"[^\n]*?tvg-chno="(\d+)"/m', $playlist, $matches, PREG_SET_ORDER);

        $ids = [];
        foreach ($matches as [, $tvgId, $number]) {
            if ($tvgId !== '') {
                $ids[(int) $number] = $tvgId;
            }
        }

        return $ids;
    }

    /**
     * Поток канала в MPEG-TS. В адресе логин и пароль Xtream — его видят только
     * свои клиенты после входа.
     */
    public function streamUrl(int $streamId): string
    {
        return "{$this->url}/live/".rawurlencode($this->username).'/'.rawurlencode($this->password)."/{$streamId}?output_format=ts";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function api(string $action): array
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->get("{$this->apiBase()}/player_api.php", [...$this->credentials(), 'action' => $action])
            ->throw()
            ->json();

        return is_array($response) ? array_values($response) : [];
    }

    private function apiBase(): string
    {
        return $this->apiUrl ?? $this->url;
    }

    /**
     * @return array{username: string, password: string}
     */
    private function credentials(): array
    {
        return ['username' => $this->username, 'password' => $this->password];
    }
}
