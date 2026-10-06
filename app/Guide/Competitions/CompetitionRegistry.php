<?php

namespace App\Guide\Competitions;

use App\Models\Competition;
use Illuminate\Support\Collection;

/**
 * Турниры каталога в базе: имя, страна, вес и эмблема, которую приносят
 * источники (ESPN, TheSportsDB).
 */
final class CompetitionRegistry
{
    /** @var Collection<string, Competition>|null */
    private ?Collection $competitions = null;

    /**
     * Каталог из кода — в таблицу (эмблемы, найденные раньше, остаются).
     */
    public function sync(): void
    {
        foreach (CompetitionCatalog::all() as $competition) {
            $model = Competition::query()->updateOrCreate(['key' => $competition['key']], [
                'sport' => $competition['sport'],
                'name' => $competition['name'],
                'short_name' => $competition['short'],
                'country' => $competition['country'],
                'weight' => $competition['weight'],
            ]);
            $this->learnLogo($model, $competition['logo'] ?? null);
        }

        $this->competitions = null;
    }

    public function resolve(string $sport, ?string $name): ?Competition
    {
        $key = CompetitionCatalog::match($sport, $name);

        return $key !== null ? $this->all()->get($key) : null;
    }

    /**
     * Эмблема от источника — если своей ещё нет.
     */
    public function learnLogo(Competition $competition, ?string $url): void
    {
        if ($url !== null && $competition->logo_url === null) {
            $competition->update(['logo_url' => $url, 'logo_path' => null, 'logo_failed_at' => null]);
        }
    }

    /**
     * @return Collection<string, Competition>
     */
    private function all(): Collection
    {
        if ($this->competitions === null || $this->competitions->isEmpty()) {
            $this->competitions = Competition::query()->get()->keyBy('key');
        }

        return $this->competitions;
    }
}
