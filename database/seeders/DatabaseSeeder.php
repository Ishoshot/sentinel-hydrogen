<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(AiOptionSeeder::class);

        // User::factory(10)->create();

        User::updateOrCreate([
            'email' => 'io.oluwatobi@gmail.com',
        ], [
            'name' => 'Oluwaseun Ishola',
            'email' => 'io.oluwatobi@gmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ]);
    }
}
