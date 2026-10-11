<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Вход ТВ-клиента по коду (RFC 8628): устройство показывает короткий код,
 * пользователь подтверждает его на сайте, устройство получает токен.
 * Код устройства хранится только хешем; запись живёт до выдачи токена.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_codes', function (Blueprint $table) {
            $table->id();
            // sha256 секрета, который знает только устройство.
            $table->string('device_code', 64)->unique();
            // Код для человека: 8 согласных без похожих букв.
            $table->string('user_code', 8)->unique();
            $table->string('device_name');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('polled_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_codes');
    }
};
