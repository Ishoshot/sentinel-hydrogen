<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    config(['briefings.data_guard.enabled' => false]);

    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->workspace->teamMembers()->create([
        'user_id' => $this->user->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);
});

it('returns workspace-level eligibility for briefings', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefings.eligibility', $this->workspace));

    $response->assertOk()
        ->assertJsonStructure([
            'can_generate',
            'restriction_reason',
        ]);
});

it('requires authentication to check briefing eligibility', function (): void {
    $response = $this->getJson(route('briefings.eligibility', $this->workspace));

    $response->assertUnauthorized();
});

it('requires workspace membership to check briefing eligibility', function (): void {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson(route('briefings.eligibility', $this->workspace));

    $response->assertForbidden();
});
