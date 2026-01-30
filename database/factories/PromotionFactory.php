<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Promotions\PromotionValueType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
final class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'code' => mb_strtoupper(fake()->unique()->bothify('PROMO-####')),
            'value_type' => PromotionValueType::Percentage->value,
            'value_amount' => fake()->randomElement([10, 15, 20, 25, 30]),
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
            'max_uses' => null,
            'times_used' => 0,
            'is_active' => true,
            'polar_discount_id' => fake()->uuid(),
        ];
    }

    /**
     * Create a percentage discount promotion.
     */
    public function percentage(int $percent = 20): static
    {
        return $this->state(fn (array $attributes): array => [
            'value_type' => PromotionValueType::Percentage->value,
            'value_amount' => $percent,
        ]);
    }

    /**
     * Create a flat amount discount promotion.
     *
     * @param  float  $dollars  The discount amount in dollars (e.g., 10.00 for $10)
     */
    public function flat(float $dollars = 10.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'value_type' => PromotionValueType::Flat->value,
            'value_amount' => $dollars,
        ]);
    }

    /**
     * Create an expired promotion.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->subDay(),
        ]);
    }

    /**
     * Create a future promotion (not yet valid).
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes): array => [
            'valid_from' => now()->addDay(),
            'valid_to' => now()->addMonth(),
        ]);
    }

    /**
     * Create an inactive promotion.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a promotion with limited uses.
     */
    public function limitedUses(int $maxUses = 100, int $timesUsed = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_uses' => $maxUses,
            'times_used' => $timesUsed,
        ]);
    }

    /**
     * Create a fully used promotion.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_uses' => 10,
            'times_used' => 10,
        ]);
    }

    /**
     * Create a promotion not synced to Polar.
     */
    public function notSynced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'polar_discount_id' => null,
        ]);
    }
}
