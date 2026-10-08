<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Откуда что взялось: идентификаторы источника для каждой сущности (с учётом
 * регистра и пространства имён), запуски импорта, наблюдения страниц и
 * покрытие — когда раздел обновлялся успешно и чем закончилась последняя попытка.
 */
return new class extends Migration
{
    /** Сущность → таблица каталога, на которую ссылается привязка. */
    private const array SOURCE_TABLES = [
        'country' => 'catalog_countries',
        'category' => 'catalog_categories',
        'team' => 'catalog_teams',
        'athlete' => 'catalog_athletes',
        'competition' => 'catalog_competitions',
        'edition' => 'catalog_editions',
        'stage' => 'catalog_stages',
        'event' => 'catalog_events',
    ];

    public function up(): void
    {
        foreach (self::SOURCE_TABLES as $entity => $target) {
            Schema::create("catalog_{$entity}_sources", function (Blueprint $table) use ($entity, $target) {
                $table->id();
                $table->foreignId('provider_id')->constrained('catalog_providers');
                // Пространство имён id у источника: participant, template, tournament, stage, event…
                $table->string('external_scope');
                // SQLite сравнивает строки с учётом регистра — id источника «QkGeVG1n» ≠ «qkgevg1n».
                $table->string('external_id');
                $table->foreignId("{$entity}_id")->constrained($target)->cascadeOnDelete();
                $table->string('url', 1024)->nullable();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->unique(['provider_id', 'external_scope', 'external_id'], "catalog_{$entity}_sources_unique");
                $table->index("{$entity}_id");
            });
        }

        Schema::create('catalog_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('catalog_providers');
            $table->string('kind');
            $table->json('scope')->nullable();
            // running, succeeded, partial, failed.
            $table->string('status')->default('running');
            $table->boolean('dry_run')->default(false);
            $table->string('parser_version');
            $table->unsignedInteger('pages_fetched')->default(0);
            $table->unsignedInteger('pages_cached')->default(0);
            $table->unsignedInteger('pages_failed')->default(0);
            $table->unsignedInteger('events_seen')->default(0);
            $table->json('diagnostics')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Одна загруженная страница: адрес, язык, проверка ответа. Сам HTML лежит в кэше, не в базе.
        Schema::create('catalog_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->nullable()->constrained('catalog_import_runs')->nullOnDelete();
            $table->foreignId('provider_id')->constrained('catalog_providers');
            $table->string('url', 1024);
            $table->string('host');
            // Язык страницы по атрибуту lang, а не по домену.
            $table->string('locale', 8)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            // valid, invalid, challenge, error.
            $table->string('status');
            $table->string('error')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('parser_version');
            $table->unsignedInteger('records')->default(0);
            $table->boolean('from_cache')->default(false);
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->index(['provider_id', 'url']);
        });

        Schema::create('catalog_coverage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('catalog_providers');
            $table->foreignId('competition_id')->constrained('catalog_competitions')->cascadeOnDelete();
            $table->foreignId('edition_id')->nullable()->constrained('catalog_editions')->nullOnDelete();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedInteger('events')->default(0);
            $table->date('window_from')->nullable();
            $table->date('window_to')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'competition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_coverage');
        Schema::dropIfExists('catalog_observations');
        Schema::dropIfExists('catalog_import_runs');
        foreach (array_keys(self::SOURCE_TABLES) as $entity) {
            Schema::dropIfExists("catalog_{$entity}_sources");
        }
    }
};
