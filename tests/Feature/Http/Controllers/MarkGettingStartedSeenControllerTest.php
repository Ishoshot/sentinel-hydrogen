<?php

declare(strict_types=1);

use App\Models\User;

it('marks getting started as seen for authenticated user', function (): void {
    $user = User::factory()->create(['has_seen_getting_started' => false]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('user.mark-getting-started-seen'));

    $response->assertOk()
        ->assertJson(['message' => 'Getting started marked as seen']);

    expect($user->fresh()->has_seen_getting_started)->toBeTrue();
});

it('returns 401 for unauthenticated requests', function (): void {
    $response = $this->postJson(route('user.mark-getting-started-seen'));

    $response->assertUnauthorized();
});

it('is idempotent when called multiple times', function (): void {
    $user = User::factory()->create(['has_seen_getting_started' => true]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson(route('user.mark-getting-started-seen'));

    $response->assertOk()
        ->assertJson(['message' => 'Getting started marked as seen']);

    expect($user->fresh()->has_seen_getting_started)->toBeTrue();
});
