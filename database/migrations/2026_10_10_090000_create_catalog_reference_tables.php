<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Каталог видов спорта (docs/sports-catalog-reorganization-plan.md, §6):
 * справочники — поставщики данных, виды спорта, дисциплины, страны, регионы,
 * разделы навигации и изображения. Старые таблицы (teams, competitions,
 * sport_events…) не трогаются: новый каталог живёт рядом, с префиксом catalog_.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('homepage')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_sports', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            // Семейство определяет модель события и страницу матча: team, racket, duel, bout, cricket, race, golf.
            $table->string('family');
            // Волна охвата из плана: A, B, C.
            $table->string('wave', 1);
            // Есть ли проверенные данные: вид спорта без них в меню помечен «нет данных».
            $table->boolean('active')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('catalog_disciplines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('catalog_sports')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->timestamps();
            $table->unique(['sport_id', 'key']);
        });

        Schema::create('catalog_regions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('catalog_regions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('catalog_assets', function (Blueprint $table) {
            $table->id();
            // logo, flag, portrait — флаг вместо эмблемы виден по виду.
            $table->string('kind');
            $table->string('url', 1024);
            $table->foreignId('provider_id')->nullable()->constrained('catalog_providers')->nullOnDelete();
            $table->string('path')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'url']);
        });

        Schema::create('catalog_countries', function (Blueprint $table) {
            $table->id();
            // Внутренний код (france, england); ISO — только известный, не выдуманный.
            $table->string('code')->unique();
            $table->string('iso2', 2)->nullable();
            $table->string('iso3', 3)->nullable();
            // country, territory, sporting_territory (Англия, Шотландия…).
            $table->string('kind')->default('country');
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('catalog_countries')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('catalog_regions')->nullOnDelete();
            $table->foreignId('flag_asset_id')->nullable()->constrained('catalog_assets')->nullOnDelete();
            $table->timestamps();
            $table->index('iso2');
        });

        // Раздел навигации внутри вида спорта: страна, регион, тур (ATP), весовая категория, игра…
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('catalog_sports')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            $table->string('kind');
            $table->string('key');
            $table->string('name');
            $table->foreignId('country_id')->nullable()->constrained('catalog_countries')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('catalog_regions')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(1000);
            $table->timestamps();
            $table->unique(['sport_id', 'key']);
        });

        // Названия и написания любой сущности: перевод, сокращение, псевдоним — с источником.
        Schema::create('catalog_names', function (Blueprint $table) {
            $table->id();
            $table->morphs('nameable');
            $table->string('locale', 8);
            // official (название источника на этом языке), short, alias.
            $table->string('kind')->default('official');
            $table->string('name');
            // Нижний регистр без диакритики — для поиска.
            $table->string('normalized');
            $table->foreignId('provider_id')->nullable()->constrained('catalog_providers')->nullOnDelete();
            $table->string('source_url', 1024)->nullable();
            // Ручная правка: импорт её не перезаписывает.
            $table->boolean('curated')->default(false);
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->unique(['nameable_type', 'nameable_id', 'locale', 'kind', 'name'], 'catalog_names_unique');
            $table->index('normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_names');
        Schema::dropIfExists('catalog_categories');
        Schema::dropIfExists('catalog_countries');
        Schema::dropIfExists('catalog_assets');
        Schema::dropIfExists('catalog_regions');
        Schema::dropIfExists('catalog_disciplines');
        Schema::dropIfExists('catalog_sports');
        Schema::dropIfExists('catalog_providers');
    }
};
