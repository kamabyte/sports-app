<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Команда — одна на все написания: телегид, ESPN, LiveScore, КХЛ…
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('sport', 32);
            $table->string('name');
            // Откуда эмблема: адрес у источника и сохранённый у нас файл.
            $table->string('logo_url', 500)->nullable();
            $table->string('logo_source', 32)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamp('logo_failed_at')->nullable();
            $table->timestamps();

            $table->index('sport');
        });

        Schema::create('team_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('sport', 32);
            // Нормализованное написание: нижний регистр, без знаков.
            $table->string('alias');
            $table->string('source', 32);
            $table->timestamps();

            $table->unique(['sport', 'alias']);
        });

        // Турниры из курируемого списка (App\Guide\Competitions\CompetitionCatalog).
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('sport', 32);
            $table->string('name');
            $table->string('short_name');
            $table->string('country', 2)->nullable();
            $table->unsignedTinyInteger('weight');
            $table->string('logo_url', 500)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamp('logo_failed_at')->nullable();
            $table->timestamps();
        });

        foreach (['sport_events', 'fixtures'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('competition_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['sport_events', 'fixtures'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('home_team_id');
                $table->dropConstrainedForeignId('away_team_id');
                $table->dropConstrainedForeignId('competition_id');
            });
        }

        Schema::dropIfExists('competitions');
        Schema::dropIfExists('team_aliases');
        Schema::dropIfExists('teams');
    }
};
