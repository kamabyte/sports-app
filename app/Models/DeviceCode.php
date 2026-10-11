<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Запрос входа ТВ-клиента по коду (docs/api.md → «Вход по коду»).
 *
 * @property int $id
 * @property string $device_code
 * @property string $user_code
 * @property string $device_name
 * @property int|null $user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $denied_at
 * @property Carbon|null $polled_at
 * @property Carbon $expires_at
 * @property-read User|null $user
 */
class DeviceCode extends Model
{
    /** Сколько живёт код. */
    public const int TTL_SECONDS = 600;

    /** Как часто устройство может спрашивать о результате. */
    public const int INTERVAL_SECONDS = 5;

    /** Согласные без похожих (нет гласных — не складываются слова). */
    private const string ALPHABET = 'BCDFGHJKLMNPQRSTVWXZ';

    private const int LENGTH = 8;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'denied_at' => 'datetime',
            'polled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Новый запрос; секрет устройства возвращается один раз — в базе только хеш.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $deviceName): array
    {
        self::query()->where('expires_at', '<', now())->delete();

        $secret = Str::random(40);
        $code = self::query()->create([
            'device_code' => self::hash($secret),
            'user_code' => self::freshUserCode(),
            'device_name' => $deviceName,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return [$code, $secret];
    }

    public static function forDevice(string $secret): ?self
    {
        return self::query()->where('device_code', self::hash($secret))->first();
    }

    /** Ожидающий решения код по вводу человека: регистр, дефис и пробелы не важны. */
    public static function pending(string $input): ?self
    {
        $code = self::normalize($input);

        return strlen($code) !== self::LENGTH ? null : self::query()
            ->where('user_code', $code)
            ->whereNull('approved_at')
            ->whereNull('denied_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public static function normalize(string $input): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper($input)) ?? '';
    }

    /** Код для показа: BCDF-GHJK. */
    public function displayCode(): string
    {
        return substr($this->user_code, 0, 4).'-'.substr($this->user_code, 4);
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Проверка слишком частых опросов; отмечает время опроса.
     */
    public function pollTooSoon(): bool
    {
        $tooSoon = $this->polled_at !== null && $this->polled_at->diffInSeconds(now()) < self::INTERVAL_SECONDS;
        $this->forceFill(['polled_at' => now()])->save();

        return $tooSoon;
    }

    private static function freshUserCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (self::query()->where('user_code', $code)->exists());

        return $code;
    }

    private static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
