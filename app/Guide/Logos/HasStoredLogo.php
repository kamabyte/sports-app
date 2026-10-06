<?php

namespace App\Guide\Logos;

/**
 * Эмблема, сохранённая у нас (logos:sync). Адрес — от корня сайта: веб
 * открывают и по домену, и по IP.
 *
 * @property string|null $logo_path
 */
trait HasStoredLogo
{
    public function logoUrl(): ?string
    {
        return $this->logo_path !== null ? '/storage/'.$this->logo_path : null;
    }
}
