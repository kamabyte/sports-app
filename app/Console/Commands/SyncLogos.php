<?php

namespace App\Console\Commands;

use App\Guide\Logos\LogoStore;
use App\Models\Competition;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

#[Signature('logos:sync')]
#[Description('Download team and competition logos from the sources into local storage')]
class SyncLogos extends Command
{
    /** Не скачивается — попробуем снова через сутки. */
    private const int RETRY_HOURS = 24;

    /** За один запуск — не больше: эмблем много, а запуск ежечасный. */
    private const int BATCH = 400;

    public function handle(LogoStore $store): int
    {
        $stored = 0;
        $failed = 0;

        foreach ([Team::class => 'teams', Competition::class => 'competitions'] as $model => $folder) {
            /** @var class-string<Team|Competition> $model */
            $pending = $model::query()
                ->whereNotNull('logo_url')
                ->whereNull('logo_path')
                ->where(fn (Builder $query) => $query->whereNull('logo_failed_at')->orWhere('logo_failed_at', '<', now()->subHours(self::RETRY_HOURS)))
                // Сначала то, что видно: команды и турниры событий телегида. Из
                // расписания их тысячи, а показываются сотни.
                ->orderByRaw($this->visibleFirst($model))
                ->orderBy('id')
                ->limit(self::BATCH)
                ->get();

            foreach ($pending as $owner) {
                $this->storeLogo($store, $owner, $folder) ? $stored++ : $failed++;
            }
        }

        $this->components->info("Logos stored: {$stored}, failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Team|Competition>  $model
     * @return literal-string
     */
    private function visibleFirst(string $model): string
    {
        return $model === Team::class
            ? 'exists (select 1 from sport_events e where e.ends_at > CURRENT_TIMESTAMP and (e.home_team_id = teams.id or e.away_team_id = teams.id)) desc'
            : 'exists (select 1 from sport_events e where e.ends_at > CURRENT_TIMESTAMP and e.competition_id = competitions.id) desc';
    }

    private function storeLogo(LogoStore $store, Team|Competition $owner, string $folder): bool
    {
        try {
            $owner->update(['logo_path' => $store->store((string) $owner->logo_url, $folder), 'logo_failed_at' => null]);

            return true;
        } catch (Throwable $exception) {
            $this->components->warn("{$owner->name}: {$exception->getMessage()}");
            $owner->update(['logo_failed_at' => now()]);

            return false;
        }
    }
}
