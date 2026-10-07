<?php

namespace App\Guide\Fixtures;

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\Matches\MatchBuilder;
use App\Guide\Teams\NationalTeams;
use App\Guide\Teams\TeamRegistry;
use App\Models\Fixture;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Загружает матчи из источников расписания и собирает из них события
 * (MatchBuilder). Телегид потом только добавляет к ним показы.
 */
final readonly class FixtureImporter
{
    /**
     * @param  list<FixtureSource>  $sources
     * @param  array<string, int>  $daysAheadBySource  медленным источникам (TheSportsDB) — меньше дней
     */
    public function __construct(
        private array $sources,
        private MatchBuilder $matches,
        private TeamRegistry $teams,
        private CompetitionRegistry $competitions,
        private int $daysBack = 0,
        private int $daysAhead = 1,
        private array $daysAheadBySource = [],
    ) {}

    public function import(): FixtureImportResult
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $this->competitions->sync();
        $counts = [];
        $errors = [];

        // Источники неофициальные и падают по одному — остальные это не останавливает.
        foreach ($this->sources as $source) {
            $counts[$source->key()] = 0;

            $lastDay = $today->addDays($this->daysAheadBySource[$source->key()] ?? $this->daysAhead);

            for ($day = $today->subDays($this->daysBack); $day->lte($lastDay); $day = $day->addDay()) {
                try {
                    $fixtures = $source->fixtures($day);
                } catch (Throwable $exception) {
                    report($exception);
                    $errors[] = "{$source->key()} {$day->toDateString()}: {$exception->getMessage()}";

                    continue;
                }

                foreach ($fixtures as $fixture) {
                    $this->save($source->key(), $fixture);
                    $counts[$source->key()]++;
                }
            }

            // Частичные сбои источника (один вид спорта, пропущенные строки) — в предупреждения.
            if ($source instanceof ReportsDiagnostics) {
                foreach ($source->diagnostics() as $diagnostic) {
                    $errors[] = "{$source->key()}: {$diagnostic}";
                }
            }

            if ($source instanceof ProvidesCompetitionLogos) {
                foreach ($source->competitionLogos() as $logo) {
                    $competition = $this->competitions->resolve($logo['sport'], $logo['name']);
                    if ($competition !== null) {
                        $this->competitions->learnLogo($competition, $logo['logo']);
                    }
                }
            }
        }

        // Матчи — из расписания: один на все источники, вчерашние тоже (к ним цепляются повторы).
        $matches = $this->matches->build($today->subDays(max(1, $this->daysBack)));

        return new FixtureImportResult($counts, $matches, $errors);
    }

    private function save(string $source, FixtureData $data): void
    {
        $competition = $this->competitions->resolve($data->sport, $data->competition);
        if ($competition !== null) {
            $this->competitions->learnLogo($competition, $data->competitionLogo);
        }

        // Сборные — по стране, с флагом; клубы — по написанию, с эмблемой источника.
        // У гонки соперников нет.
        $national = $data->hasTeams() ? NationalTeams::match((string) $data->home, (string) $data->away) : null;
        if (! $data->hasTeams()) {
            [$home, $away] = [null, null];
        } elseif ($national !== null) {
            $home = $this->teams->national($data->sport, $data->home, $national[0]['code'], $national[0]['category'], $source);
            $away = $this->teams->national($data->sport, $data->away, $national[1]['code'], $national[1]['category'], $source);
        } else {
            $home = $this->teams->fromSource($data->sport, (string) $data->home, $source, $data->homeLogo);
            $away = $this->teams->fromSource($data->sport, (string) $data->away, $source, $data->awayLogo);
        }

        Fixture::query()->updateOrCreate(['source' => $source, 'external_id' => $data->externalId], [
            'home_team_id' => $home?->id,
            'away_team_id' => $away?->id,
            'competition_id' => $competition?->id,
            'sport' => $data->sport,
            'competition' => $data->competition,
            'competition_logo' => $data->competitionLogo,
            'round' => $data->round,
            'title' => $data->title,
            'home' => $data->home,
            'away' => $data->away,
            'home_logo' => $data->homeLogo,
            'away_logo' => $data->awayLogo,
            'starts_at' => $data->startsAt,
            'status' => $data->status,
            'home_score' => $data->homeScore,
            'away_score' => $data->awayScore,
        ]);
    }
}
