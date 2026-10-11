<?php

namespace App\Providers;

use App\Guide\Competitions\CompetitionRegistry;
use App\Guide\DatabaseEventCatalog;
use App\Guide\Dispatcharr\XtreamClient;
use App\Guide\EventCatalog;
use App\Guide\Fake\FakeEventCatalog;
use App\Guide\Fixtures\EspnSource;
use App\Guide\Fixtures\EuroleagueSource;
use App\Guide\Fixtures\FixtureImporter;
use App\Guide\Fixtures\FixtureSource;
use App\Guide\Fixtures\FlashscoreSource;
use App\Guide\Fixtures\IbuSource;
use App\Guide\Fixtures\KhlSource;
use App\Guide\Fixtures\LiveScoreSource;
use App\Guide\Fixtures\TheSportsDbSource;
use App\Guide\Listings\ChannelMatcher;
use App\Guide\Listings\GuideFiles;
use App\Guide\Listings\GuideImporter;
use App\Guide\Listings\GuideSource;
use App\Guide\Listings\TitleParser;
use App\Guide\Listings\XmltvReader;
use App\Guide\Matches\AiringMatcher;
use App\Guide\Matches\LegacyAirings;
use App\Guide\Matches\MatchBuilder;
use App\Guide\Teams\TeamRegistry;
use App\Sports\Broadcasts\CatalogAirings;
use App\Sports\Broadcasts\EventBroadcasts;
use App\Sports\Broadcasts\EventCandidates;
use App\Sports\Broadcasts\EventMatcher;
use App\Sports\Catalog\CatalogWriter;
use App\Sports\CatalogDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Один реестр на загрузку: в нём кэш написаний команд и турниров.
        $this->app->scoped(TeamRegistry::class);
        $this->app->scoped(CompetitionRegistry::class);
        $this->app->scoped(AiringMatcher::class);

        $this->app->singleton(XtreamClient::class, fn () => new XtreamClient(
            url: rtrim((string) config('sports.providers.dispatcharr.url'), '/'),
            username: (string) config('sports.providers.dispatcharr.username'),
            password: (string) config('sports.providers.dispatcharr.password'),
            apiUrl: filled($apiUrl = config('sports.providers.dispatcharr.api_url')) ? rtrim((string) $apiUrl, '/') : null,
        ));

        $this->app->bind(EventBroadcasts::class, fn () => new EventBroadcasts(
            client: $this->app->make(XtreamClient::class),
            provider: (string) config('sports.providers.dispatcharr.name'),
            preferredLanguages: config('sports.preferred_languages', []),
        ));

        $this->app->bind(GuideImporter::class, fn () => new GuideImporter(
            client: $this->app->make(XtreamClient::class),
            files: new GuideFiles((int) config('sports.guides.refresh_hours')),
            reader: new XmltvReader,
            channelMatcher: new ChannelMatcher,
            parser: new TitleParser,
            // Каталог — показы событий каталога; прежнее приложение — показы его матчей.
            target: CatalogDatabase::standalone()
                ? new CatalogAirings(new EventMatcher(new EventCandidates, config('sports.guides.slots', [])))
                : new LegacyAirings($this->app->make(AiringMatcher::class), $this->app->make(CompetitionRegistry::class)),
            sources: GuideSource::fromConfig(config('sports.guides.sources', [])),
            daysAhead: (int) config('sports.guides.days_ahead'),
            overrides: config('sports.guides.channels', []),
            categoryLanguages: config('sports.providers.dispatcharr.category_languages', []),
        ));

        $this->app->bind(FixtureImporter::class, fn () => new FixtureImporter(
            sources: $this->fixtureSources(),
            matches: new MatchBuilder,
            teams: $this->app->make(TeamRegistry::class),
            competitions: $this->app->make(CompetitionRegistry::class),
            daysBack: (int) config('sports.fixtures.days_back'),
            daysAhead: (int) config('sports.fixtures.days_ahead'),
            daysAheadBySource: [
                'thesportsdb' => (int) config('sports.fixtures.sources.thesportsdb.days_ahead'),
                'flashscore' => min((int) config('sports.fixtures.days_ahead'), (int) config('sports.fixtures.sources.flashscore.days_ahead')),
            ],
        ));

        // Один каталог на запрос: фейковый кэширует сгенерированные дни.
        $this->app->scoped(EventCatalog::class, fn () => match (config('sports.source')) {
            'fake' => new FakeEventCatalog(
                provider: config('sports.providers.dispatcharr.name'),
                preferredLanguages: config('sports.preferred_languages'),
            ),
            default => new DatabaseEventCatalog(
                client: $this->app->make(XtreamClient::class),
                provider: config('sports.providers.dispatcharr.name'),
                preferredLanguages: config('sports.preferred_languages'),
            ),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Названия каталога (catalog_names) ссылаются на сущности короткими именами, а не классами.
        Relation::morphMap(CatalogWriter::MORPHS);

        // {event} — событие каталога (не модель); здесь, а не в routes/*.php,
        // чтобы привязка работала и с route:cache.
        Route::bind('event', fn (string $id) => app(EventCatalog::class)->find($id) ?? abort(404));

        // `composer dev` поднимает и планировщик: он загружает телегид.
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('schedule:work', 'schedule');
        }
    }

    /**
     * Включённые источники расписания матчей (sports.fixtures.sources).
     *
     * @return list<FixtureSource>
     */
    private function fixtureSources(): array
    {
        $config = config('sports.fixtures.sources');
        $enabled = fn (string $source) => (bool) ($config[$source]['enabled'] ?? false);

        return array_values(array_filter([
            $enabled('khl') ? new KhlSource : null,
            $enabled('espn') ? new EspnSource($config['espn']['leagues']) : null,
            $enabled('euroleague') ? new EuroleagueSource : null,
            $enabled('ibu') ? new IbuSource : null,
            $enabled('livescore') ? new LiveScoreSource($config['livescore']['sports']) : null,
            $enabled('thesportsdb') ? new TheSportsDbSource(
                key: (string) $config['thesportsdb']['key'],
                sports: $config['thesportsdb']['sports'],
                pauseSeconds: (int) $config['thesportsdb']['pause_seconds'],
            ) : null,
            $enabled('flashscore') ? new FlashscoreSource(
                sports: $config['flashscore']['sports'],
                timezone: (string) $config['flashscore']['timezone'],
                pauseSeconds: (int) $config['flashscore']['pause_seconds'],
                cacheHours: (int) $config['flashscore']['cache_hours'],
            ) : null,
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
