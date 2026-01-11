<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Run;
use Illuminate\Database\Seeder;

final class RunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Run::factory()->count(5)->create();
    }
}
