<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Billing\PlanTier;
use App\Models\Plan;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

final class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var list<int> $scopedPlanIds */
        $scopedPlanIds = Plan::query()
            ->whereIn('tier', [PlanTier::Illuminate->value, PlanTier::Orchestrate->value])
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $promotions = [
            [
                'name' => 'Launch Special',
                'description' => 'Special discount for early adopters during launch period.',
                'code' => 'NEWLAUNCH2026',
                'value_type' => 'percentage',
                'value_amount' => 100,
                'valid_from' => now(),
                'valid_to' => now()->addMonths(3),
                'max_uses' => 2,
                'is_active' => true,
                'eligible_plan_ids' => $scopedPlanIds !== [] ? $scopedPlanIds : null,
            ],
            [
                'name' => 'General Welcome',
                'description' => 'General purpose welcome discount for any paid checkout.',
                'code' => 'WELCOME10',
                'value_type' => 'percentage',
                'value_amount' => 10,
                'valid_from' => now(),
                'valid_to' => now()->addMonths(6),
                'max_uses' => 100,
                'is_active' => true,
                'eligible_plan_ids' => null,
            ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::updateOrCreate(
                ['code' => $promotion['code']],
                $promotion
            );
        }
    }
}
