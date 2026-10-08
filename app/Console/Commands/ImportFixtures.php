<?php

namespace App\Console\Commands;

use App\Guide\Fixtures\FixtureImporter;
use App\Sports\CatalogDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('fixtures:import')]
#[Description('Import match schedules from the sports APIs and build the events from them')]
class ImportFixtures extends Command
{
    public function handle(FixtureImporter $importer): int
    {
        // Самостоятельный каталог: прежние источники и IPTV не делают ни одного запроса.
        if (CatalogDatabase::standalone()) {
            $this->components->warn('Disabled in standalone mode (SPORTS_MODE=standalone): the sports catalog uses catalog:import.');

            return self::SUCCESS;
        }

        try {
            $result = $importer->import();
        } catch (Throwable $exception) {
            $this->components->error("Import failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        foreach ($result->errors as $error) {
            $this->components->warn($error);
        }

        $sources = collect($result->fixtures)->map(fn (int $count, string $source) => "{$source} {$count}")->implode(', ');
        $this->components->info("Fixtures: {$sources}; matches: {$result->matches}.");

        return $result->failed() ? self::FAILURE : self::SUCCESS;
    }
}
