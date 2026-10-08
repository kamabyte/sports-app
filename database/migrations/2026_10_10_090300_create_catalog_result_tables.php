<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Результаты по видам спорта — отдельными таблицами с понятными полями, а не
 * «атрибутами»: счёт по периодам/сетам/фреймам, сеты тенниса с тай-брейками,
 * иннинги крикета, исход боя, протокол гонки, раунды гольфа, таблицы.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Итог и отрезки очного матча: total, period, half, quarter, set, overtime, penalties, frame, map, leg.
        Schema::create('catalog_score_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('catalog_events')->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedTinyInteger('ordinal')->default(0);
            $table->unsignedTinyInteger('side');
            $table->integer('value')->nullable();
            $table->boolean('completed')->default(true);
            $table->timestamps();
            $table->unique(['event_id', 'kind', 'ordinal', 'side']);
        });

        Schema::create('catalog_tennis_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('catalog_events')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->unsignedTinyInteger('side');
            $table->unsignedTinyInteger('games');
            // Очки стороны в тай-брейке сета (7-6(3) — у одной 7, у другой 3). null — тай-брейка не было.
            $table->unsignedTinyInteger('tiebreak')->nullable();
            // regular или match_tiebreak (решающий тай-брейк до 10).
            $table->string('kind')->default('regular');
            $table->boolean('completed')->default(true);
            $table->timestamps();
            $table->unique(['event_id', 'set_number', 'side']);
        });

        Schema::create('catalog_cricket_innings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('catalog_events')->cascadeOnDelete();
            $table->unsignedTinyInteger('side');
            $table->unsignedTinyInteger('innings_order');
            $table->unsignedSmallInteger('runs')->nullable();
            $table->unsignedTinyInteger('wickets')->nullable();
            // Корректные подачи, а не «овер.мяч» десятичной дробью.
            $table->unsignedSmallInteger('legal_balls')->nullable();
            $table->boolean('declared')->default(false);
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->unique(['event_id', 'innings_order']);
        });

        Schema::create('catalog_bout_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('catalog_events')->cascadeOnDelete();
            $table->unsignedTinyInteger('winner_side')->nullable();
            // win, draw, no_contest.
            $table->string('outcome');
            $table->string('method')->nullable();
            $table->unsignedTinyInteger('round')->nullable();
            $table->unsignedSmallInteger('time_seconds')->nullable();
            $table->timestamps();
        });

        // Протокол гонки, сессии, этапа или турнира с таблицей лидеров.
        Schema::create('catalog_classification_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('catalog_events')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('catalog_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->boolean('tied')->default(false);
            // finished, running, dnf, dns, dsq, cut, withdrawn, unknown — не «нулевое время».
            $table->string('status')->default('unknown');
            $table->unsignedBigInteger('time_ms')->nullable();
            $table->unsignedBigInteger('gap_ms')->nullable();
            $table->unsignedSmallInteger('laps')->nullable();
            $table->decimal('points', 8, 2)->nullable();
            // Гольф: итог относительно пара и сумма ударов.
            $table->smallInteger('to_par')->nullable();
            $table->unsignedSmallInteger('strokes')->nullable();
            $table->string('raw_result')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'entry_id']);
        });

        Schema::create('catalog_golf_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('catalog_events')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('catalog_entries')->cascadeOnDelete();
            $table->unsignedTinyInteger('round');
            $table->unsignedSmallInteger('strokes')->nullable();
            $table->smallInteger('to_par')->nullable();
            $table->unsignedTinyInteger('holes_completed')->nullable();
            $table->string('status')->default('finished');
            $table->timestamps();
            $table->unique(['event_id', 'entry_id', 'round']);
        });

        // Турнирная таблица — как её показал источник в момент наблюдения; правила не пересчитываются.
        Schema::create('catalog_standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained('catalog_editions')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('catalog_stages')->nullOnDelete();
            $table->foreignId('entry_id')->constrained('catalog_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('played')->nullable();
            $table->unsignedSmallInteger('won')->nullable();
            $table->unsignedSmallInteger('drawn')->nullable();
            $table->unsignedSmallInteger('lost')->nullable();
            $table->unsignedSmallInteger('scored')->nullable();
            $table->unsignedSmallInteger('conceded')->nullable();
            $table->decimal('points', 8, 2)->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
            $table->unique(['edition_id', 'stage_id', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_standings');
        Schema::dropIfExists('catalog_golf_rounds');
        Schema::dropIfExists('catalog_classification_results');
        Schema::dropIfExists('catalog_bout_results');
        Schema::dropIfExists('catalog_cricket_innings');
        Schema::dropIfExists('catalog_tennis_sets');
        Schema::dropIfExists('catalog_score_segments');
    }
};
