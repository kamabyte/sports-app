<?php

namespace App\Sports\Catalog;

use Illuminate\Support\Str;
use Normalizer;

/**
 * Нормализация названий для поиска: нижний регистр, без диакритики, ё → е,
 * без пунктуации. Кириллица остаётся кириллицей — русский поиск ищет по-русски.
 */
final class Names
{
    public static function normalize(string $name): string
    {
        $decomposed = Normalizer::normalize($name, Normalizer::FORM_D) ?: $name;
        // Диакритика латиницы уходит, но «й» должна остаться «й», а не «и».
        $decomposed = strtr($decomposed, ["и\u{0306}" => 'й', "И\u{0306}" => 'Й']);
        $plain = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);
        $plain = strtr($plain, ['ё' => 'е', 'Ё' => 'Е']);
        $plain = mb_strtolower($plain);
        $plain = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $plain);

        return trim($plain);
    }

    /** Часть адреса: латиница из любого письма («Франция» → «frantsiia»). */
    public static function slug(string $name): string
    {
        return Str::slug(Str::ascii($name)) ?: 'item';
    }
}
