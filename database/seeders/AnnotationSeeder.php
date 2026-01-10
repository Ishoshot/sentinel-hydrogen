<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Annotation;
use Illuminate\Database\Seeder;

final class AnnotationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Annotation::factory()->count(10)->create();
    }
}
