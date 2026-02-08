<?php

declare(strict_types=1);

use App\Enums\Billing\PlanTier;
use App\Models\Plan;
use App\Models\User;

it('lists available plans with pricing for authenticated users', function (): void {
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

it('lists available plans without authentication for public access', function (): void {
    Plan::factory()->illuminate()->create();
    Plan::factory()->create(); // Foundation (default)

    $response = $this->getJson(route('plans.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'tier',
                    'name',
                    'description',
                    'monthly_runs_limit',
                    'monthly_commands_limit',
                    'team_size_limit',
                    'runs_label',
                    'commands_label',
                    'team_size_label',
                    'price',
                    'price_label',
                    'period',
                    'price_monthly_cents',
                    'price_monthly',
                    'features',
                    'feature_list',
                    'support',
                    'highlighted',
                    'cta',
                    'cta_link',
                    'color',
                ],
            ],
        ]);
});

it('returns all plan tiers ordered by price', function (): void {
    Plan::factory()->create(); // Foundation (default)
    Plan::factory()->illuminate()->create();
    Plan::factory()->orchestrate()->create();
    Plan::factory()->sanctum()->create();

    $response = $this->getJson(route('plans.index'));

    $response->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(4);
    expect($data[0]['tier'])->toBe('foundation');
    expect($data[1]['tier'])->toBe('illuminate');
    expect($data[2]['tier'])->toBe('orchestrate');
    expect($data[3]['tier'])->toBe('sanctum');
});
