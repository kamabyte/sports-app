<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Турниры, их сезоны (editions), стадии и события. Турнир — постоянная
 * сущность («Премьер-лига»), сезон — его розыгрыш («2026/2027»), стадия —
 * группа, раунд, сетка или сессия. Событие — матч, бой, сессия, гонка…
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_competitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('catalog_sports')->cascadeOnDelete();
            $table->foreignId('discipline_id')->nullable()->constrained('catalog_disciplines')->nullOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('slug');
            // Основной раздел навигации — для адреса и хлебных крошек; остальные — в catalog_competition_category.
            $table->foreignId('primary_category_id')->constrained('catalog_categories');
            // league, cup, tournament, series, unknown.
            $table->string('kind')->default('unknown');
            // domestic, international, unknown.
            $table->string('scope')->default('unknown');
            // Участники — клубы, сборные или спортсмены: club, national, individual, unknown.
            $table->string('participant_kind')->default('unknown');
            // team, singles, doubles, mixed_doubles, individual, relay.
            $table->string('entry_format')->default('team');
            $table->string('gender')->nullable();
            $table->string('age_group')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('catalog_countries')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('catalog_regions')->nullOnDelete();
            $table->foreignId('logo_asset_id')->nullable()->constrained('catalog_assets')->nullOnDelete();
            $table->string('identity_state')->default('provisional');
            // Чем меньше, тем выше в списках.
            $table->unsignedSmallInteger('position')->default(1000);
            $table->timestamps();
            $table->unique(['primary_category_id', 'slug']);
            $table->index('sport_id');
        });

        // Турнир может быть в нескольких разделах (чемпионат мира — и в «Европе», и в «Мире»).
        Schema::create('catalog_competition_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('catalog_competitions')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('catalog_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1000);
            $table->timestamps();
            $table->unique(['competition_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('catalog_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('catalog_competitions')->cascadeOnDelete();
            // Устойчивый внутренний ключ: «2026-2027», «2026».
            $table->string('key');
            $table->string('label');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['competition_id', 'key']);
        });

        Schema::create('catalog_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('catalog_editions')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('catalog_stages')->nullOnDelete();
            // league, group, qualification, main_draw, playoff, round, session, stage, other.
            $table->string('kind')->default('other');
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index('edition_id');
        });

        Schema::create('catalog_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('catalog_competitions')->cascadeOnDelete();
            $table->foreignId('edition_id')->nullable()->constrained('catalog_editions')->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('catalog_stages')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('catalog_events')->nullOnDelete();
            // match, tie, bout, meeting, session, race, round, tournament.
            $table->string('kind');
            $table->string('name')->nullable();
            // Подпись тура/раунда как у источника: «Round 5», «Final».
            $table->string('round_label')->nullable();
            // Дата по часовому поясу источника — известна, даже если время нет.
            $table->date('date')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // exact, date, unknown — «Время уточняется», а не полночь.
            $table->string('time_precision')->default('exact');
            $table->string('source_timezone')->nullable();
            // scheduled, live, finished, postponed, cancelled, abandoned, interrupted, unknown.
            $table->string('status')->default('scheduled');
            // retired, walkover, after_extra_time, after_penalties, awarded…
            $table->string('status_detail')->nullable();
            // Как статус записан у источника: для разбора новых кодов.
            $table->string('raw_status')->nullable();
            // none, partial, final.
            $table->string('result_state')->default('none');
            $table->unsignedTinyInteger('winner_side')->nullable();
            $table->string('venue')->nullable();
            $table->string('result_text')->nullable();
            // published, hidden — скрытое не показывается, но не удаляется.
            $table->string('publication_state')->default('published');
            $table->timestamp('last_observed_at')->nullable();
            $table->timestamps();
            $table->index(['date', 'status']);
            $table->index(['competition_id', 'date']);
            $table->index(['edition_id', 'date']);
            $table->index('starts_at');
            $table->index('parent_id');
        });

        // Участник события: сторона 1/2 в очном матче, позиция — в гонке и турнирной таблице.
        Schema::create('catalog_event_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('catalog_events')->cascadeOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('catalog_entries')->nullOnDelete();
            $table->unsignedTinyInteger('side')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            // home, away, neutral; null — неизвестно. В теннисе в интерфейсе не показывается.
            $table->string('home_away')->nullable();
            $table->string('seed')->nullable();
            // Подпись неизвестного участника («Победитель матча 5») — вместо поддельного участника.
            $table->string('label')->nullable();
            // retired, walkover, disqualified…
            $table->string('entry_status')->nullable();
            // Команда, за которую выступает спортсмен в этом событии (Формула-1: «Alpine»).
            $table->string('affiliation')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'entry_id']);
            $table->unique(['event_id', 'side']);
            $table->index('entry_id');
        });

        Schema::create('catalog_edition_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('catalog_editions')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('catalog_entries')->cascadeOnDelete();
            $table->string('affiliation')->nullable();
            $table->timestamps();
            $table->unique(['edition_id', 'entry_id']);
            $table->index('entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_edition_entries');
        Schema::dropIfExists('catalog_event_entries');
        Schema::dropIfExists('catalog_events');
        Schema::dropIfExists('catalog_stages');
        Schema::dropIfExists('catalog_editions');
        Schema::dropIfExists('catalog_competition_category');
        Schema::dropIfExists('catalog_competitions');
    }
};
