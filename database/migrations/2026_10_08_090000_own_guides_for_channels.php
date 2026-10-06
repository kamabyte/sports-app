<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Телегид — свой, а не из Dispatcharr: канал помнит свой tvg-id из плейлиста
 * и телегид, к которому привязан (источник sports.guides.sources и id канала в нём).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropIndex(['epg_id']);
            $table->dropColumn('epg_id');
            $table->string('tvg_id')->nullable()->after('logo');
            $table->string('guide_source', 32)->nullable()->after('tvg_id');
            $table->string('guide_channel')->nullable()->after('guide_source');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['tvg_id', 'guide_source', 'guide_channel']);
            $table->string('epg_id')->nullable()->index();
        });
    }
};
