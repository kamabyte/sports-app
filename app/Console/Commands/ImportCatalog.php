<?php

namespace App\Console\Commands;

use App\Sports\Flashscore\FlashscoreImporter;
use App\Sports\Flashscore\PageFetcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;

#[Signature('catalog:import
    {--sport=* : Only these sports (catalog keys)}
    {--page=* : Only these configured page paths}
    {--dry-run : Fetch, parse and resolve, then roll back}
    {--no-translations : Skip the Russian-language pages}
    {--force : Run even when FLASHSCORE_ENABLED is off (a deliberate, bounded manual run)}')]
#[Description('Import the sports catalog from the configured public Flashscore competition and team pages')]
class ImportCatalog extends Command
{
    public function handle(): int
    {
        if (! config('sports.catalog.flashscore.enabled') && ! $this->option('force')) {
            $this->components->warn('Flashscore import is off (FLASHSCORE_ENABLED). Enabling it is the owner\'s call; --force runs it once.');

            return self::SUCCESS;
        }

        $sports = array_values(array_filter((array) $this->option('sport'), 'is_string')) ?: null;
        $pages = array_values(array_filter((array) $this->option('page'), 'is_string')) ?: null;
        if (FlashscoreImporter::pages($sports, $pages) === []) {
            $this->components->error('No configured pages match.');

            return self::FAILURE;
        }

        $config = config('sports.catalog.flashscore');
        $importer = new FlashscoreImporter(new PageFetcher(
            hosts: $config['hosts'],
            pauseSeconds: (float) $config['pause_seconds'],
            cacheHours: (int) $config['cache_hours'],
            timeoutSeconds: (int) $config['timeout_seconds'],
        ));

        try {
            // Без --sport и --page — плановый запуск: дневные списки и бюджет страниц.
            $run = $importer->run($sports, $pages, (bool) $this->option('dry-run'), ! $this->option('no-translations'), planned: $sports === null && $pages === null);
        } catch (LockTimeoutException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($run->diagnostics ?? [] as $line) {
            str_contains($line, 'FAILED') ? $this->components->warn($line) : $this->line("  {$line}");
        }
        $this->components->info(sprintf(
            '%s: %d pages fetched, %d from cache, %d failed; %d events.%s',
            ucfirst($run->status), $run->pages_fetched, $run->pages_cached, $run->pages_failed, $run->events_seen,
            $run->dry_run ? ' Dry run — nothing saved.' : '',
        ));

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
