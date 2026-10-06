<?php

namespace App\Guide;

/**
 * Молодёжная (u21, u19…) или женская команда / турнир; '' — взрослые мужчины.
 * «England U21» — не «Англия», «Ireland Women» — не «Ирландия».
 */
final class TeamCategory
{
    public static function of(?string $text): string
    {
        $text ??= '';
        $age = preg_match('/(?<![\p{L}\d])(?:U|U-|до\s)(\d{2})(?!\d)/iu', $text, $match) ? 'u'.$match[1] : '';
        // «Kazakhstan W» — одиночная заглавная W в конце названия.
        $women = preg_match('/(?<!\p{L})(women|woman|ladies|female|женщин|женск|жін|девуш|ženy|nők|női|feminin|femenin|féminin|femmes|damen|kobiet)/iu', $text)
            || preg_match('/(?<!\p{L})W$/u', trim($text)) ? 'w' : '';

        return $age.$women;
    }
}
