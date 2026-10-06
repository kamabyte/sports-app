<?php

namespace App\Console\Commands;

use App\Guide\Listings\GuideImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('guide:import')]
#[Description('Import channels from Dispatcharr, read the TV guides and attach broadcasts to the matches')]
class ImportGuide extends Command
{
    public function handle(GuideImporter $importer): int
    {
        try {
            $result = $importer->import();
        } catch (Throwable $exception) {
            $this->components->error("Import failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        foreach ($result->errors as $error) {
            $this->components->warn("Guide not updated: {$error}");
        }

        $this->components->info(sprintf(
            'Channels: %d (with a guide: %d), sports programmes: %d, matched airings: %d, matches with broadcasts: %d.',
            $result->channels,
            $result->withGuide,
            $result->programmes,
            $result->airings,
            $result->events,
        ));

        return self::SUCCESS;
    }
}
