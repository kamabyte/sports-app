<?php

namespace App\Sports\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Длинные списки (результаты сезона, матчи команды) — порциями: «Показать
 * ещё» увеличивает limit в адресе, так что обновление и «назад» сохраняют
 * показанное.
 */
final class Paging
{
    public const int STEP = 60;

    private const int MAX = 1200;

    public static function limit(Request $request, int $step = self::STEP): int
    {
        return max($step, min(self::MAX, $request->integer('limit') ?: $step));
    }

    /**
     * Порция и признак, что есть ещё: выборка берётся на одну запись больше.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $items
     * @return array{0: Collection<int, T>, 1: bool}
     */
    public static function slice(Collection $items, int $limit): array
    {
        return [$items->take($limit), $items->count() > $limit];
    }
}
