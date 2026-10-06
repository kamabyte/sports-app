<?php

namespace App\Guide\Logos;

use GdImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Эмблема из источника — к нам: вписывается в 256×256 с прозрачностью и
 * сохраняется в WebP на диске public. Браузер и ТВ-клиенты берут её у
 * приложения, а не с чужих CDN.
 */
final readonly class LogoStore
{
    private const int SIZE = 256;

    private const int QUALITY = 85;

    private const int MAX_BYTES = 3 * 1024 * 1024;

    private const int TIMEOUT_SECONDS = 15;

    /**
     * @return string путь на диске public
     */
    public function store(string $url, string $folder): string
    {
        // Имя файла — из адреса: тот же адрес уже скачан — качать незачем.
        $path = "logos/{$folder}/".sha1($url).'.webp';
        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        $body = Http::timeout(self::TIMEOUT_SECONDS)->get($url)->throw()->body();

        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('The logo is too large.');
        }

        // SVG и прочее, чего GD не читает, — не эмблема для нас.
        $source = @imagecreatefromstring($body);
        if ($source === false) {
            throw new RuntimeException('The logo is not an image GD can read.');
        }

        Storage::disk('public')->put($path, $this->webp($this->fit($source)));

        return $path;
    }

    private function fit(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::SIZE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function webp(GdImage $image): string
    {
        ob_start();
        imagewebp($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }
}
