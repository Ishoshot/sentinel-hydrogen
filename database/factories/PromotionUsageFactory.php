<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Promotions\PromotionUsageStatus;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromotionUsage>
 */
final class PromotionUsageFactory extends Factory
{
    protected $model = PromotionUsage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'promotion_id' => Promotion::factory(),
            'workspace_id' => Workspace::factory(),
            'status' => PromotionUsageStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => PromotionUsageStatus::Completed,
            'confirmed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PromotionUsageStatus::Failed,
        ]);
    }
}
