<?php

namespace App\Sports\Catalog;

use RuntimeException;

/** Данные нарушают правило каталога (вид спорта, состав пары, циклы…) — не сохраняются. */
final class InvalidCatalogData extends RuntimeException {}
