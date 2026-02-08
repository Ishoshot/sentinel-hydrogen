<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
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

    $this->briefing = Briefing::factory()->system()->create(['is_active' => true]);

    $this->generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create(['generated_by_id' => $this->user->id]);
});

// --- Store ---

it('creates a share link for a completed generation', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-generations.share', [
            $this->workspace,
            $this->generation,
        ]), [
            'expires_in_days' => 7,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['id', 'token', 'expires_at', 'is_active'],
            'message',
        ]);

    $this->assertDatabaseHas('briefing_shares', [
        'briefing_generation_id' => $this->generation->id,
        'workspace_id' => $this->workspace->id,
        'is_active' => true,
    ]);
});

it('creates a share link with password and access limit', function (): void {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-generations.share', [
            $this->workspace,
            $this->generation,
        ]), [
            'expires_in_days' => 30,
            'password' => 'secret123',
            'max_accesses' => 50,
        ]);

    $response->assertCreated();

    $share = BriefingShare::query()->latest('id')->first();
    expect($share->max_accesses)->toBe(50)
        ->and($share->password_hash)->not->toBeNull();
});

it('rejects sharing an incomplete generation', function (): void {
    $pendingGeneration = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->pending()
        ->create(['generated_by_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-generations.share', [
            $this->workspace,
            $pendingGeneration,
        ]), [
            'expires_in_days' => 7,
        ]);

    $response->assertBadRequest()
        ->assertJson(['message' => 'Cannot share a briefing that is not yet complete.']);
});

it('returns 404 for generation from another workspace', function (): void {
    $otherWorkspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
    $otherGeneration = BriefingGeneration::factory()
        ->forWorkspace($otherWorkspace)
        ->completed()
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson(route('briefing-generations.share', [
            $this->workspace,
            $otherGeneration,
        ]), [
            'expires_in_days' => 7,
        ]);

    $response->assertNotFound();
});

it('forbids regular members from creating share links', function (): void {
    $member = User::factory()->create();
    $this->workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $response = $this->actingAs($member, 'sanctum')
        ->postJson(route('briefing-generations.share', [
            $this->workspace,
            $this->generation,
        ]), [
            'expires_in_days' => 7,
        ]);

    $response->assertForbidden();
});

it('requires authentication to create share link', function (): void {
    $response = $this->postJson(route('briefing-generations.share', [
        $this->workspace,
        $this->generation,
    ]), [
        'expires_in_days' => 7,
    ]);

    $response->assertUnauthorized();
});

// --- Destroy ---

it('revokes a share link', function (): void {
    $share = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->create([
            'created_by_id' => $this->user->id,
        ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson(route('briefing-shares.destroy', [
            $this->workspace,
            $share,
        ]));

    $response->assertSuccessful()
        ->assertJson(['message' => 'Share link revoked successfully.']);

    expect($share->fresh()->is_active)->toBeFalse();
});

it('returns 404 when revoking share from another workspace', function (): void {
    $otherWorkspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
    $otherGeneration = BriefingGeneration::factory()
        ->forWorkspace($otherWorkspace)
        ->completed()
        ->create();

    $share = BriefingShare::factory()
        ->forGeneration($otherGeneration)
        ->create();

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson(route('briefing-shares.destroy', [
            $this->workspace,
            $share,
        ]));

    $response->assertNotFound();
});

it('forbids regular members from revoking share links', function (): void {
    $member = User::factory()->create();
    $this->workspace->teamMembers()->create([
        'user_id' => $member->id,
        'team_id' => $this->workspace->team->id,
        'workspace_id' => $this->workspace->id,
        'role' => 'member',
        'joined_at' => now(),
    ]);

    $share = BriefingShare::factory()
        ->forGeneration($this->generation)
        ->create(['created_by_id' => $this->user->id]);

    $response = $this->actingAs($member, 'sanctum')
        ->deleteJson(route('briefing-shares.destroy', [
            $this->workspace,
            $share,
        ]));

    $response->assertForbidden();
});
