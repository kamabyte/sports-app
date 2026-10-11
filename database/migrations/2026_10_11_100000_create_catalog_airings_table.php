<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Показы событий каталога на каналах Dispatcharr: передача телегида,
 * узнанная как это событие (прямой эфир или повтор). Показ только
 * дополняет событие — видимость событий от него не зависит.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_airings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('catalog_events')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            // Название передачи в телегиде — как её узнали.
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('live')->default(false);
            $table->boolean('replay')->default(false);
            $table->timestamps();
            $table->index(['event_id', 'starts_at']);
            $table->index('starts_at');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_airings');
    }
};
