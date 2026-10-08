<?php

namespace App\Sports\Flashscore;

use RuntimeException;

/** Источник просит подождать (429): импорт останавливается, а не повторяет запросы. */
final class RateLimited extends RuntimeException {}
