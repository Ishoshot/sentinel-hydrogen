<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@sentinel.dev'],
            [
                'name' => 'System Admin',
                'email' => 'admin@sentinel.dev',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }
}
