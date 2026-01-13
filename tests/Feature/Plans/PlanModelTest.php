<?php

declare(strict_types=1);

use App\Models\Plan;
use Brick\Money\Money;

it('casts plan pricing and features correctly', function (): void {
    $plan = Plan::factory()->create([
        'price_monthly' => 4900,
        'features' => [
            'byok_enabled' => true,
            'custom_guidelines' => false,
        ],
    ]);

    expect($plan->price_monthly)->toBeInstanceOf(Money::class)
        ->and($plan->price_monthly?->getMinorAmount()->toInt())->toBe(4900)
        ->and($plan->hasFeature('byok_enabled'))->toBeTrue()
        ->and($plan->hasFeature('custom_guidelines'))->toBeFalse();
});
