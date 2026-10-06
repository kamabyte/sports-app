<?php

namespace App\Guide;

/**
 * Насколько турнир «большой» (им сортируются витрина и полки) и чья у него
 * страна, если в названии передачи её нет.
 */
final class CompetitionRank
{
    private const int DEFAULT_WEIGHT = 4;

    /**
     * Вид спорта → [шаблон названия турнира, вес, страна].
     *
     * @var array<string, list<array{0: string, 1: int, 2: ?string}>>
     */
    private const array RANKS = [
        'football' => [
            ['/лига чемпионов/u', 10, 'EU'],
            ['/(^|\s)апл|английск|премьер-лига$/u', 9, 'GB'],
            ['/рпл|российская премьер|мир рпл/u', 8, 'RU'],
            ['/лига наций|nemzetek/u', 8, 'EU'],
            ['/ла лига|испани/u', 8, 'ES'],
            ['/бундеслига|германи/u', 8, 'DE'],
            ['/лига европы/u', 7, 'EU'],
            ['/серия а|итальянская серия|итали/u', 7, 'IT'],
            ['/лига 1|франци/u', 7, 'FR'],
            ['/лига конференций/u', 6, 'EU'],
            ['/лига pari|кубок/u', 5, null],
        ],
        'hockey' => [
            ['/кхл/u', 8, 'RU'],
            ['/нхл/u', 8, 'US'],
            ['/вхл|мхл/u', 4, 'RU'],
        ],
        'basketball' => [
            ['/нба/u', 8, 'US'],
            ['/евролига/u', 7, 'EU'],
            ['/втб/u', 6, 'RU'],
            ['/еврокубок/u', 5, 'EU'],
        ],
        'motorsport' => [
            ['/формула[- ]1|forma-1/u', 9, null],
        ],
        'combat' => [
            ['/ufc/u', 8, 'US'],
        ],
        'tennis' => [
            ['/atp|wta/u', 6, null],
        ],
    ];

    /**
     * @return array{weight: int, country: ?string}
     */
    public static function of(string $sport, ?string $competition): array
    {
        $name = mb_strtolower($competition ?? '');

        foreach (self::RANKS[$sport] ?? [] as [$pattern, $weight, $country]) {
            if (preg_match($pattern, $name)) {
                return ['weight' => $weight, 'country' => $country];
            }
        }

        return ['weight' => self::DEFAULT_WEIGHT, 'country' => null];
    }
}
