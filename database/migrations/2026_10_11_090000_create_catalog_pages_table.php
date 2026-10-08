<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Реестр страниц источника: все турниры из публичной карты сайта, отметка,
 * играют ли они сегодня-завтра (по дневным спискам), когда страница
 * импортировалась и переводилась. По нему импорт выбирает, что загрузить в
 * этот запуск, — в пределах бюджета запросов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('catalog_providers');
            $table->foreignId('sport_id')->constrained('catalog_sports')->cascadeOnDelete();
            $table->string('path')->unique();
            // config (список в настройках), sitemap (карта сайта), lite (дневной список).
            $table->string('origin');
            // Заголовок турнира в дневном списке: «ENGLAND: Premier League».
            $table->string('listed_as')->nullable();
            // Последний день, когда у турнира были события в дневном списке.
            $table->date('listed_on')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['sport_id', 'listed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_pages');
    }
};
