<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Finding;
use Illuminate\Database\Seeder;

final class FindingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Finding::factory()->count(10)->create();
    }
}
