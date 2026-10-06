<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Учётная запись для локальной разработки, пароль — «password».
        // Настоящие заводятся командой php artisan sports:user.
        User::factory()->create([
            'name' => 'Demo',
            'email' => 'demo@sports.test',
        ]);
    }
}
