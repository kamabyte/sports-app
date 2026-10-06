<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Каналы источника (Dispatcharr, Xtream Codes).
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('stream_id')->unique();
            $table->unsignedInteger('number');
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('epg_id')->nullable()->index();
            $table->string('category')->nullable();
            $table->string('language', 8)->nullable();
            $table->timestamps();
        });

        // Событие, собранное из записей телегида на разных каналах.
        Schema::create('sport_events', function (Blueprint $table) {
            $table->id();
            $table->string('sport', 32);
            $table->string('title');
            $table->string('home')->nullable();
            $table->string('away')->nullable();
            $table->string('competition')->nullable();
            $table->string('competition_key')->nullable()->index();
            $table->string('country', 2)->nullable();
            $table->unsignedTinyInteger('weight')->default(4);
            $table->string('round')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            // Всё, по чему ищут, в нижнем регистре: LIKE в SQLite не знает кириллицу.
            $table->text('search');
            $table->timestamps();

            $table->index(['sport', 'starts_at']);
        });

        // Показ события на канале: прямой эфир, одновременный показ или повтор.
        Schema::create('airings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->boolean('live')->default(false);
            $table->boolean('replay')->default(false);
            $table->timestamps();

            $table->unique(['channel_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airings');
        Schema::dropIfExists('sport_events');
        Schema::dropIfExists('channels');
    }
};
