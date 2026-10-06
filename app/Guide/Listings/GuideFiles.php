<?php

namespace App\Guide\Listings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Файлы телегидов в storage/app/guides: скачиваются раз в refreshHours
 * (EPG One — ~50 МБ), как есть — .gz или нет, XmltvReader читает оба.
 * Не скачалось — остаётся прежний файл.
 */
final readonly class GuideFiles
{
    private const int TIMEOUT_SECONDS = 300;

    public function __construct(
        private int $refreshHours,
    ) {}

    /**
     * Путь к файлу телегида и ошибка загрузки, если взят прежний файл.
     *
     * @return array{path: string, error: ?string}
     *
     * @throws RuntimeException не скачался и прежнего нет
     */
    public function get(GuideSource $source): array
    {
        $disk = Storage::disk('local');
        $file = "guides/{$source->key}";
        $fresh = "guides.{$source->key}.fresh";

        if ($disk->exists($file) && Cache::has($fresh)) {
            return ['path' => $disk->path($file), 'error' => null];
        }

        try {
            $body = Http::timeout(self::TIMEOUT_SECONDS)->get($source->url)->throw()->body();
            if (! $this->isGuide($body)) {
                throw new RuntimeException('not an XMLTV file');
            }

            $disk->put($file, $body);
            Cache::put($fresh, true, now()->addHours($this->refreshHours));

            return ['path' => $disk->path($file), 'error' => null];
        } catch (Throwable $exception) {
            if (! $disk->exists($file)) {
                throw new RuntimeException("{$source->name}: {$exception->getMessage()}", previous: $exception);
            }

            return ['path' => $disk->path($file), 'error' => "{$source->name}: {$exception->getMessage()}"];
        }
    }

    /** XMLTV (или он же в gzip), а не страница с ошибкой. */
    private function isGuide(string $body): bool
    {
        if (! str_starts_with($body, "\x1f\x8b")) {
            return str_contains(substr($body, 0, 4096), '<tv');
        }

        // Начало gzip распаковываем потоком: gzdecode обрезанный архив не берёт.
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return false;
        }
        fwrite($stream, substr($body, 0, 65536));
        rewind($stream);
        stream_filter_append($stream, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 31]);
        $head = (string) @fread($stream, 4096);
        fclose($stream);

        return str_contains($head, '<tv');
    }
}
