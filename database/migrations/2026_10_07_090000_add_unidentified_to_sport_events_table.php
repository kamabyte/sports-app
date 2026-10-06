<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sport_events', function (Blueprint $table) {
            // Командный спорт без команд («Чемпионат КХЛ»): не событие, пока
            // матч не узнан по расписанию (fixture_id), — в списки не попадает.
            $table->boolean('unidentified')->default(false)->after('round')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sport_events', function (Blueprint $table) {
            $table->dropColumn('unidentified');
        });
    }
};
