<?php

namespace App\Sports\Flashscore;

use RuntimeException;

/** Ответ — не страница с данными (проверка, другая разметка, ошибка): сбой, а не пустой список. */
final class InvalidPage extends RuntimeException {}
