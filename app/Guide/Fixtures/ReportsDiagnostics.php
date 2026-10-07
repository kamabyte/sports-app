<?php

namespace App\Guide\Fixtures;

/**
 * Источник, у которого загрузка может удаться частично: один вид спорта не
 * загрузился, строки пропущены. Остальное он отдаёт как обычно, а это —
 * предупреждения fixtures:import.
 */
interface ReportsDiagnostics
{
    /**
     * @return list<string>
     */
    public function diagnostics(): array;
}
