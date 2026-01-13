<?php

declare(strict_types=1);

use App\Enums\PlanTier;
use App\Models\Plan;
use App\Models\User;

it('lists available plans with pricing', function (): void {
    $user = User::factory()->create();
    Plan::factory()->illuminate()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('plans.index'));

    $response->assertOk()
        ->assertJsonFragment([
            'tier' => PlanTier::Illuminate->value,
            'price_monthly_cents' => 2000,
        ]);
});
