<?php

namespace App\Console\Commands;

use App\Sports\Flashscore\SitemapDiscovery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('catalog:discover {--force : Run even when FLASHSCORE_ENABLED is off}')]
#[Description('Register every competition page of the catalog sports from the public Flashscore sitemap')]
class DiscoverCatalog extends Command
{
    public function handle(): int
    {
        if (! config('sports.catalog.flashscore.enabled') && ! $this->option('force')) {
            $this->components->warn('Flashscore is off (FLASHSCORE_ENABLED); --force runs it once.');

            return self::SUCCESS;
        }

        try {
            $counts = (new SitemapDiscovery((string) config('sports.catalog.flashscore.hosts.en'), (float) config('sports.catalog.flashscore.pause_seconds')))->discover();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $sport => $count) {
            $this->line("  {$sport}: {$count}");
        }
        $this->components->info(array_sum($counts).' competition pages in the sitemap.');

        return self::SUCCESS;
    }
}
