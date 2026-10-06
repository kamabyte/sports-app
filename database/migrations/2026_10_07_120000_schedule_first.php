<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Расписание — источник событий: событие (sport_events) — это матч из
 * fixtures, телегид только добавляет к нему показы. Один матч из нескольких
 * источников — один sport_events (fixtures.sport_event_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        // События старой схемы (собранные из телегида) пересобираются из расписания.
        DB::table('airings')->delete();
        DB::table('sport_events')->delete();

        Schema::table('fixtures', function (Blueprint $table) {
            $table->foreignId('sport_event_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('sport_events', function (Blueprint $table) {
            $table->dropIndex(['unidentified']);
            $table->dropColumn('unidentified');
        });
    }

    public function down(): void
    {
        Schema::table('sport_events', function (Blueprint $table) {
            $table->boolean('unidentified')->default(false)->index();
        });

        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sport_event_id');
        });
    }
};
