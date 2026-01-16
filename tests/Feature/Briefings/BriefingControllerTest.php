<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
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

    $this->briefing = Briefing::factory()->system()->create([
        'slug' => 'standup-update',
        'title' => 'Daily Standup Update',
        'is_active' => true,
    ]);
});

it('lists available briefings for workspace', function (): void {
    Briefing::factory()->system()->create(['is_active' => true, 'title' => 'Weekly Summary']);
    Briefing::factory()->system()->create(['is_active' => false, 'title' => 'Inactive']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefings.index', $this->workspace));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'slug', 'description', 'icon'],
            ],
        ]);

    // Should only return active briefings
    $data = $response->json('data');
    expect(collect($data)->pluck('title'))->not->toContain('Inactive');
});

it('shows a specific briefing with generation info', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefings.show', [$this->workspace, $this->briefing->slug]));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'title', 'slug', 'description'],
            'can_generate',
            'restriction_reason',
        ]);
});

it('returns 404 for inactive briefing', function (): void {
    $inactiveBriefing = Briefing::factory()->system()->inactive()->create(['slug' => 'inactive-briefing']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefings.show', [$this->workspace, $inactiveBriefing->slug]));

    $response->assertNotFound();
});

it('returns 404 for non-existent briefing slug', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson(route('briefings.show', [$this->workspace, 'non-existent-slug']));

    $response->assertNotFound();
});

it('requires authentication to list briefings', function (): void {
    $response = $this->getJson(route('briefings.index', $this->workspace));

    $response->assertUnauthorized();
});

it('requires workspace membership to list briefings', function (): void {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson(route('briefings.index', $this->workspace));

    $response->assertForbidden();
});
