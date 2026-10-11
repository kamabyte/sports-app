<?php

namespace App\Guide\Fixtures;

use App\Guide\Teams\NationalTeams;

/**
 * Похожесть названий команд на разных языках: телегид — по-русски,
 * расписание — по-английски. Оба названия переводятся в латиницу и
 * сравниваются по словам: «Динамо (Москва)» ≈ «Dynamo Moscow»,
 * «Салават Юлаев (Уфа)» ≈ «Salavat Yulaev Ufa».
 */
final class NameSimilarity
{
    private const string TRANSLITERATION = 'Russian-Latin/BGN; Any-Latin; Latin-ASCII; Lower()';

    /** Русские названия, которые на латинице звучат иначе, чем по-английски. */
    private const array EXONYMS = [
        'moskva' => 'moscow', 'sankt' => 'saint', 'peterburg' => 'petersburg', 'bavariya' => 'bayern',
        'myunkhen' => 'munich', 'yuventus' => 'juventus', 'siti' => 'city', 'yunayted' => 'united',
        'rim' => 'roma', 'neapol' => 'napoli', 'lissabon' => 'lisbon', 'sportinh' => 'sporting',
        // «ЦСКА» по-английски — CSKA, а не TSSKA.
        'tsska' => 'cska', 'nyukasl' => 'newcastle',
        // Польские названия городов: «Bayern Monachium», «RB Lipsk», «Inter Mediolan».
        'monachium' => 'munich', 'lipsk' => 'leipzig', 'mediolan' => 'milan', 'brema' => 'bremen',
        // Брюгге по-французски и по-итальянски: «Club Bruges».
        'bruges' => 'brugge',
    ];

    /**
     * Слова, которые есть у многих клубов: одного такого совпадения мало —
     * «Реал Мадрид» не «Real Cartagena», «Аль-Хиляль» не «Al Fateh».
     */
    private const array GENERIC = [
        'al', 'el', 'real', 'dinamo', 'dynamo', 'united', 'city', 'sporting', 'atletico', 'athletic', 'olympique',
        'racing', 'union', 'lokomotiv', 'spartak', 'torpedo', 'metallurg', 'hapoel', 'maccabi', 'inter', 'san',
        'santa', 'saint', 'st', 'new', 'royal', 'deportivo', 'town', 'rovers', 'wanderers', 'county', 'borussia',
        'slavia', 'sparta', 'young', 'boys', 'fortuna', 'vitoria', 'academy', 'akademia', 'basket', 'basketball',
        'volley', 'handball', 'hockey', 'women', 'u19', 'u21',
        // «Paris FC» — не «Paris Saint-Germain»: город, а не клуб.
        'paris',
    ];

    private const array NOISE = ['fc', 'fk', 'hc', 'hk', 'bc', 'bk', 'cf', 'sc', 'ac', 'club', 'the'];

    /** Слова короче — только точное совпадение (с поправкой на звучание). */
    private const int MIN_FUZZY_LENGTH = 5;

    /** Похожие, но не одинаковые слова («Барселона» ~ «Baskonia», 71 %) — только половина совпадения… */
    private const float FUZZY_WEIGHT = 0.5;

    /** …а очень похожие («Тоттенхэм» ~ «Tottenham», 88 %) — целое. */
    private const int CLOSE_WORD_SIMILARITY = 85;

    /** Слова похожи, если совпадает начало такой длины… */
    private const int PREFIX = 5;

    /** …или звучат похоже хотя бы на столько процентов (similar_text по фонетике). */
    private const int MIN_WORD_SIMILARITY = 70;

    /**
     * Доля слов более короткого названия, нашедшихся в другом: 0…1.
     */
    /**
     * Сравнений много (передачи × матчи), а названий мало — результат кэшируется.
     *
     * @var array<string, float>
     */
    private static array $scores = [];

    /** Больше сравнений в кэше не держим: у каталога названий десятки тысяч. */
    private const int MAX_CACHED = 200_000;

    public static function score(string $a, string $b): float
    {
        if (count(self::$scores) >= self::MAX_CACHED) {
            self::$scores = [];
        }

        return self::$scores[$a."\0".$b] ??= self::compare($a, $b);
    }

    /**
     * Грубые ключи названия: первая буква звучания каждого слова и код
     * страны сборной. Похожие названия (score > 0) делят хотя бы один ключ —
     * по ним можно не сравнивать заведомо разные: «Ньюкасл» (n) и «Newcastle» (n),
     * «Венгрия» и «Hungary» (national:HU).
     *
     * @return list<string>
     */
    public static function keys(string $name): array
    {
        $national = NationalTeams::parse($name);
        if ($national !== null) {
            return ['national:'.$national['code']];
        }

        // «Йокерит» (iokerit) и «Jokerit»: j и i звучат одинаково в начале слова.
        return array_values(array_unique(array_map(
            fn (string $word) => strtr(self::phonetic($word)[0] ?? '', ['j' => 'i']),
            self::words($name),
        )));
    }

    private static function compare(string $a, string $b): float
    {
        // Две сборные сравниваются по стране и категории: «Ирландия» (Ireland) —
        // не «Iceland», «Турция» — это и «Türkiye», и «Turkey».
        $left = NationalTeams::parse($a);
        $right = NationalTeams::parse($b);
        if ($left !== null && $right !== null) {
            return $left['code'] === $right['code'] ? 1.0 : 0.0;
        }

        $left = self::words($a);
        $right = self::words($b);

        if ($left === [] || $right === []) {
            return 0.0;
        }

        [$short, $long] = count($left) <= count($right) ? [$left, $right] : [$right, $left];
        $found = array_values(array_filter($short, fn (string $word) => array_any($long, fn (string $other) => self::sameWord($word, $other))));

        // Одни сокращения — не совпадение: «MG» и «GO» (штаты Бразилии) — не
        // «Магдебург» и «Ганновер», «PS» — не «Палмейрас». «Металлург Мг» — да:
        // «Металлург» совпал сам по себе.
        if ($found !== [] && ! array_any($found, fn (string $word) => array_any($long, fn (string $other) => self::sameWord($word, $other, abbreviations: false)))) {
            return 0.0;
        }

        // Совпало только общее слово, а у названия есть и своё — это не та команда.
        $distinctive = array_diff($short, self::GENERIC);
        if ($distinctive !== [] && array_diff($found, self::GENERIC) === []) {
            return 0.0;
        }

        // «Atletico FC» — одни общие слова, а у «Атлетико Мадрид» есть своё:
        // это в лучшем случае половина совпадения.
        if ($distinctive === [] && array_diff($long, self::GENERIC) !== []) {
            return min(0.5, self::weight($short, $long));
        }

        return self::weight($short, $long);
    }

    /**
     * Доля слов короткого названия, нашедшихся в длинном. Название из одного
     * слова, которое только похоже, — половина совпадения: «Барселона» ~
     * «Baskonia» (71 %) — не одно и то же. В длинных названиях похожее слово
     * засчитывается целиком: «Иржи Легечка» — «Jiri Lehecka».
     *
     * @param  list<string>  $short
     * @param  list<string>  $long
     */
    private static function weight(array $short, array $long): float
    {
        $sum = 0.0;
        foreach ($short as $word) {
            $score = max([0.0, ...array_map(fn (string $other) => self::wordScore($word, $other), $long)]);
            $sum += count($short) > 1 && $score > 0.0 ? 1.0 : $score;
        }

        return $sum / count($short);
    }

    /**
     * Слова названий (транслитерация дорогая, а сравнений — события × матчи).
     *
     * @var array<string, list<string>>
     */
    private static array $words = [];

    /**
     * @return list<string>
     */
    private static function words(string $name): array
    {
        return self::$words[$name] ??= self::split($name);
    }

    /**
     * @return list<string>
     */
    private static function split(string $name): array
    {

        // «Ньюкасл» → «n'yukasl», «Тоттенхэм» → «tottenkh·em»: знаки транслитерации слово не делят.
        $latin = str_replace(["'", '·', 'ʹ', '"'], '', (string) transliterator_transliterate(self::TRANSLITERATION, $name));
        $words = preg_split('/[^a-z0-9]+/', $latin, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_map(fn (string $word) => self::EXONYMS[$word] ?? $word, $words);

        return array_values(array_filter(
            array_diff($words, self::NOISE),
            // «U21», «2» — не про название команды.
            fn (string $word) => strlen($word) >= 2 && ! ctype_digit($word),
        ));
    }

    private static function sameWord(string $a, string $b, bool $abbreviations = true): bool
    {
        return self::wordScore($a, $b, $abbreviations) > 0.0;
    }

    /** 1 — то же слово, FUZZY_WEIGHT — только похоже, 0 — разные. */
    private static function wordScore(string $a, string $b, bool $abbreviations = true): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $length = min(strlen($a), strlen($b));
        if ($length >= self::PREFIX && strncmp($a, $b, self::PREFIX) === 0) {
            return 1.0;
        }

        if ($abbreviations && (self::abbreviates($a, $b) || self::abbreviates($b, $a))) {
            return 1.0;
        }

        [$left, $right] = [self::phonetic($a), self::phonetic($b)];
        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percent);

        // Короткие слова — только точно: «СКА» не «ЦСКА», «МХК» не «ХК».
        // Длина тоже должна быть близкой: «niderlandi» и «ireland» похожи буквами, но не звучанием.
        if ($length < self::MIN_FUZZY_LENGTH || $percent < self::MIN_WORD_SIMILARITY
            || abs(strlen($left) - strlen($right)) > max(2, intdiv(max(strlen($left), strlen($right)), 3))) {
            return 0.0;
        }

        return $percent >= self::CLOSE_WORD_SIMILARITY ? 1.0 : self::FUZZY_WEIGHT;
    }

    /**
     * Сокращение: «Мг» — «Магнитогорск» (mg — буквы magnitogorsk по порядку,
     * с той же первой). Только из двух букв: трёхбуквенные — это уже слова
     * («Ben» — не сокращение «Bayern»). И не общие слова: «el» — не «Elversberg»,
     * «mg» — не «metallurg».
     */
    private static function abbreviates(string $short, string $long): bool
    {
        if (strlen($short) !== 2 || strlen($long) < 5 || $short[0] !== $long[0]
            || in_array($short, self::GENERIC, true) || in_array($long, self::GENERIC, true)) {
            return false;
        }

        $position = 0;
        foreach (str_split($short) as $letter) {
            $position = strpos($long, $letter, $position);
            if ($position === false) {
                return false;
            }
            $position++;
        }

        return true;
    }

    /**
     * Звучание вместо написания: по-русски английские названия пишут на
     * слух — «Ньюкасл» (nyukasl) и «Newcastle», «Лидс» (lids) и «Leeds».
     */
    private static function phonetic(string $word): string
    {
        $word = strtr($word, ['ck' => 'k', 'ph' => 'f', 'kh' => 'h', 'yu' => 'u', 'ya' => 'a', 'ee' => 'i', 'ea' => 'i', 'oo' => 'u', 'ou' => 'u', 'tz' => 'z', 'ts' => 'z', 'x' => 'ks']);
        $word = strtr($word, ['c' => 'k', 'q' => 'k', 'w' => 'v', 'y' => 'i']);
        $word = (string) preg_replace('/(.)\1+/', '$1', $word);

        return strlen($word) > 3 ? (string) preg_replace('/e$/', '', $word) : $word;
    }
}
