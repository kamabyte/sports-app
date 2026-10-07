<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * События без соперников из расписания: сессия Формулы-1, гонка биатлона.
 * У них нет команд, только название («Гран-при Бахрейна», «Спринт · Мужчины»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->string('home')->nullable()->change();
            $table->string('away')->nullable()->change();
            $table->string('title')->nullable()->after('round');
        });
    }

    public function down(): void
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn('title');
            $table->string('home')->nullable(false)->change();
            $table->string('away')->nullable(false)->change();
        });
    }
};
