<?php

declare(strict_types=1);

use App\Actions\Plans\ListPlans;
use App\Models\Plan;

it('lists all plans', function (): void {
    Plan::factory()->count(3)->create();

    $action = new ListPlans;
    $result = $action->handle();

    expect($result)->toHaveCount(3);
});

it('orders plans by price monthly ascending', function (): void {
    Plan::factory()->create(['price_monthly' => 5000]);
    Plan::factory()->create(['price_monthly' => 1000]);
    Plan::factory()->create(['price_monthly' => 3000]);

    $action = new ListPlans;
    $result = $action->handle();

    // Get amounts in cents from Money objects
    $prices = $result->map(fn ($plan) => $plan->price_monthly->getMinorAmount()->toInt())->toArray();

    expect($prices[0])->toBe(1000);
    expect($prices[1])->toBe(3000);
    expect($prices[2])->toBe(5000);
});

it('returns empty collection when no plans exist', function (): void {
    $action = new ListPlans;
    $result = $action->handle();

    expect($result)->toBeEmpty();
});
