<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use App\Support\PlanDefaults;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (PlanDefaults::all() as $tier => $attributes) {
            Plan::updateOrCreate(
                ['tier' => $tier],
                $attributes
            );
        }
    }
}
