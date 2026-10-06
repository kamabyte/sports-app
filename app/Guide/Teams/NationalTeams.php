<?php

namespace App\Guide\Teams;

use App\Guide\TeamCategory;
use Locale;

/**
 * Сборные: страна по названию команды — на языках телегидов (LOCALES), с
 * категорией или без («Франция», «France», «Türkiye», «Англия», «Ireland
 * Women», «France U21»). Названия стран — из ICU, плюс написания, в которых
 * ICU и источники расходятся, и сборные Великобритании (у них свои флаги).
 *
 * Флаги — flagcdn.com по коду (ISO 3166-1, «gb-eng» и т. п.).
 */
final class NationalTeams
{
    private const string FLAG_URL = 'https://flagcdn.com/w320/%s.png';

    /** Сборные Великобритании играют отдельно и под своими флагами. */
    private const array HOME_NATIONS = [
        'англия' => 'gb-eng', 'england' => 'gb-eng',
        'шотландия' => 'gb-sct', 'scotland' => 'gb-sct',
        'уэльс' => 'gb-wls', 'wales' => 'gb-wls',
        'северная ирландия' => 'gb-nir', 'northern ireland' => 'gb-nir',
    ];

    /** Написания, в которых расходятся ICU и источники. */
    private const array VARIANTS = [
        'turkey' => 'tr', 'турция' => 'tr', 'czech republic' => 'cz', 'bosnia & herzegovina' => 'ba',
        'bosnia herzegovina' => 'ba', 'bosnia' => 'ba', 'usa' => 'us', 'united states' => 'us', 'сша' => 'us',
        'ivory coast' => 'ci', "cote d'ivoire" => 'ci', 'кот-д’ивуар' => 'ci', 'cape verde' => 'cv',
        'south korea' => 'kr', 'korea republic' => 'kr', 'корея' => 'kr', 'южная корея' => 'kr',
        'north korea' => 'kp', 'кндр' => 'kp', 'macedonia' => 'mk', 'фарерские острова' => 'fo',
        'republic of ireland' => 'ie', 'holland' => 'nl', 'голландия' => 'nl', 'russia' => 'ru',
        'chinese taipei' => 'tw', 'dr congo' => 'cd', 'kyrgyzstan' => 'kg', 'киргизия' => 'kg',
        'uae' => 'ae', 'оаэ' => 'ae', 'iran' => 'ir', 'syria' => 'sy', 'vietnam' => 'vn',
        'moldova' => 'md', 'kosovo' => 'xk', 'česká republika' => 'cz', 'ceska republika' => 'cz',
    ];

    /**
     * Не сборные, хотя ICU знает их как регионы («Европа», «Мир»), и старые
     * коды, которые делят название с нынешними: FX («Франция»), SU («СССР»/Россия), YU…
     */
    private const array NOT_COUNTRIES = ['eu', 'ez', 'un', 'qo', 'zz', 'aq', 'fx', 'su', 'yu', 'dd', 'zr', 'tp', 'cs', 'bu', 'an', 'nt', 'yd', 'ac', 'cp', 'dg', 'ea', 'ic', 'ta', 'xa', 'xb'];

    /** Языки телегидов. Первые важнее: при совпадении названий побеждает русское и английское. */
    private const array LOCALES = ['ru', 'en', 'uk', 'cs', 'sk', 'hu', 'ro', 'sr_Latn', 'hr', 'pl', 'de', 'fr', 'it', 'es', 'pt'];

    /** @var array<string, string>|null название (нижний регистр) → код */
    private static ?array $codes = null;

    /**
     * Сборная ли это: код страны и категория (u21, w…), иначе null.
     *
     * @return array{code: string, category: string}|null
     */
    public static function parse(?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        $category = TeamCategory::of($name);
        $country = self::normalize(self::stripCategory($name));
        $code = self::codes()[$country] ?? null;

        return $code !== null ? ['code' => $code, 'category' => $category] : null;
    }

    /**
     * Матч сборных: страны с обеих сторон. «Монако — Тулуза» — это Лига 1,
     * а не сборная Монако.
     *
     * @return array{0: array{code: string, category: string}, 1: array{code: string, category: string}}|null
     */
    public static function match(?string $home, ?string $away): ?array
    {
        $home = self::parse($home);
        $away = self::parse($away);

        return $home !== null && $away !== null ? [$home, $away] : null;
    }

    public static function flagUrl(string $code): string
    {
        return sprintf(self::FLAG_URL, $code);
    }

    /**
     * «France U21» → «France», «Kazakhstan W» → «Kazakhstan», «Ирландия (ж)» → «Ирландия».
     */
    private static function stripCategory(string $name): string
    {
        $name = (string) preg_replace('/\([^)]*\)/u', ' ', $name);
        $name = (string) preg_replace('/(?<![\p{L}\d])(U-?\d{2}|до\s\d{2}|women|woman|ladies|female|женщины|жен\.?|w)(?![\p{L}\d])/iu', ' ', $name);

        return trim($name);
    }

    private static function normalize(string $name): string
    {
        $name = str_replace(['ё', '’', '`'], ['е', "'", "'"], mb_strtolower(trim($name)));

        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['-', '–', '&'], [' ', ' ', '&'], $name)));
    }

    /**
     * @return array<string, string>
     */
    private static function codes(): array
    {
        if (self::$codes !== null) {
            return self::$codes;
        }

        $codes = [];
        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $code = strtolower($first.$second);
                if (in_array($code, self::NOT_COUNTRIES, true)) {
                    continue;
                }

                foreach (self::LOCALES as $locale) {
                    $name = Locale::getDisplayRegion("und_{$first}{$second}", $locale);
                    // Неизвестный код ICU возвращает как есть.
                    if (is_string($name) && $name !== '' && $name !== "{$first}{$second}") {
                        $codes[self::normalize($name)] ??= $code;
                        $codes[self::normalize((string) transliterator_transliterate('Any-Latin; Latin-ASCII', $name))] ??= $code;
                    }
                }
            }
        }

        foreach ([...self::VARIANTS, ...self::HOME_NATIONS] as $name => $code) {
            $codes[self::normalize($name)] = $code;
        }

        // «Великобритания» как сборная не играет (кроме Олимпиад) — флаги у её частей.
        return self::$codes = $codes;
    }
}
