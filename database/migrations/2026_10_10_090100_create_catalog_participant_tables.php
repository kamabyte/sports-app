<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Участники: команды (клубы и сборные), спортсмены и заявки (entries) —
 * единица участия: команда, один спортсмен, пара в теннисе, эстафета.
 * Пара не становится командой: у её спортсменов остаются свои профили.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('catalog_sports')->cascadeOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained('catalog_disciplines')->nullOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('code', 8)->nullable();
            // club, national, unknown.
            $table->string('kind')->default('unknown');
            // men, women, mixed; null — неизвестно.
            $table->string('gender')->nullable();
            // senior, u23, u21, u20, u19…; null — неизвестно.
            $table->string('age_group')->nullable();
            // first, reserve, olympic; null — неизвестно.
            $table->string('squad')->nullable();
            // Страна сборной или подтверждённая страна клуба. Не из раздела навигации.
            $table->foreignId('country_id')->nullable()->constrained('catalog_countries')->nullOnDelete();
            $table->foreignId('parent_team_id')->nullable()->constrained('catalog_teams')->nullOnDelete();
            $table->foreignId('logo_asset_id')->nullable()->constrained('catalog_assets')->nullOnDelete();
            // provisional — найдена по косвенным данным; verified — по идентификатору источника.
            $table->string('identity_state')->default('provisional');
            $table->string('slug');
            $table->timestamps();
            $table->index(['sport_id', 'kind']);
            $table->index(['country_id', 'sport_id']);
        });

        Schema::create('catalog_athletes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('portrait_asset_id')->nullable()->constrained('catalog_assets')->nullOnDelete();
            $table->string('identity_state')->default('provisional');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('catalog_athlete_sports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained('catalog_athletes')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('catalog_sports')->cascadeOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained('catalog_disciplines')->nullOnDelete();
            $table->timestamps();
            $table->unique(['athlete_id', 'sport_id', 'discipline_id']);
        });

        // Гражданство, под флагом которого выступает спортсмен, — с источником и датами, если известны.
        Schema::create('catalog_athlete_nationalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained('catalog_athletes')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('catalog_countries')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('catalog_providers')->nullOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->unique(['athlete_id', 'country_id', 'provider_id']);
        });

        Schema::create('catalog_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('catalog_sports')->cascadeOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained('catalog_disciplines')->nullOnDelete();
            // team, individual, pair, relay.
            $table->string('kind');
            $table->foreignId('team_id')->nullable()->constrained('catalog_teams')->cascadeOnDelete();
            // Внутренняя привязка без поддельных id источника: team:5, individual:12, pair:12-34.
            $table->string('resolution_key')->unique();
            $table->timestamps();
        });

        Schema::create('catalog_entry_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('catalog_entries')->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained('catalog_athletes')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();
            $table->unique(['entry_id', 'athlete_id']);
            $table->unique(['entry_id', 'position']);
            $table->index('athlete_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_entry_members');
        Schema::dropIfExists('catalog_entries');
        Schema::dropIfExists('catalog_athlete_nationalities');
        Schema::dropIfExists('catalog_athlete_sports');
        Schema::dropIfExists('catalog_athletes');
        Schema::dropIfExists('catalog_teams');
    }
};
