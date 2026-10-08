<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подпись тура/раунда на языке интерфейса — со страницы источника на этом
 * языке, по id события. Оригинальная подпись (round_label) остаётся.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_events', function (Blueprint $table) {
            $table->string('round_label_local')->nullable()->after('round_label');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_events', function (Blueprint $table) {
            $table->dropColumn('round_label_local');
        });
    }
};
