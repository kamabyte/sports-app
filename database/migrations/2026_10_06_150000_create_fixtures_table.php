<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Матчи из спортивных API (TheSportsDB и др.): точное время, счёт, эмблемы.
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('external_id');
            $table->string('sport', 32);
            $table->string('competition')->nullable();
            $table->string('competition_logo')->nullable();
            $table->string('round')->nullable();
            $table->string('home');
            $table->string('away');
            $table->string('home_logo')->nullable();
            $table->string('away_logo')->nullable();
            $table->timestamp('starts_at')->index();
            // upcoming, live, finished, cancelled
            $table->string('status', 16);
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['sport', 'starts_at']);
        });

        Schema::table('sport_events', function (Blueprint $table) {
            $table->foreignId('fixture_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sport_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixture_id');
        });
        Schema::dropIfExists('fixtures');
    }
};
