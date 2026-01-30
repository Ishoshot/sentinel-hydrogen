<?php

declare(strict_types=1);

use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;

it('logs activity when workspace is created', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson(route('workspaces.store'), [
            'name' => 'Test Workspace',
        ]);

    $workspace = Workspace::where('name', 'Test Workspace')->first();

    expect($workspace)->not->toBeNull();

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::WorkspaceCreated->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_id)->toBe($user->id)
        ->and($activity->description)->toContain('Test Workspace');
});

it('logs activity when workspace is updated', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner, 'sanctum')
        ->patchJson(route('workspaces.update', $workspace), [
            'name' => 'Updated Workspace Name',
        ]);

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::WorkspaceUpdated->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_id)->toBe($owner->id);
});

it('logs activity when member is invited', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($owner, 'sanctum')
        ->postJson(route('invitations.store', $workspace), [
            'email' => 'invited@example.com',
            'role' => 'member',
        ]);

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::MemberInvited->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->actor_id)->toBe($owner->id)
        ->and($activity->description)->toContain('invited@example.com');
});

it('can list activities for a workspace', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    // Create some activities
    Activity::factory()
        ->forWorkspace($workspace)
        ->byActor($owner)
        ->count(3)
        ->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(route('activities.index', $workspace));

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'type_label',
                    'description',
                    'is_system_action',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
});

it('can filter activities by type', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    Activity::factory()
        ->forWorkspace($workspace)
        ->type(ActivityType::WorkspaceCreated)
        ->create();

    Activity::factory()
        ->forWorkspace($workspace)
        ->type(ActivityType::MemberInvited)
        ->count(2)
        ->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(route('activities.index', ['workspace' => $workspace, 'type' => 'member.invited']));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('can filter activities by category', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    Activity::factory()
        ->forWorkspace($workspace)
        ->type(ActivityType::WorkspaceCreated)
        ->create();

    Activity::factory()
        ->forWorkspace($workspace)
        ->type(ActivityType::MemberInvited)
        ->create();

    Activity::factory()
        ->forWorkspace($workspace)
        ->type(ActivityType::MemberJoined)
        ->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(route('activities.index', ['workspace' => $workspace, 'category' => 'member']));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('cannot view activities from another workspace', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    Activity::factory()
        ->forWorkspace($workspace)
        ->create();

    $response = $this->actingAs($otherUser, 'sanctum')
        ->getJson(route('activities.index', $workspace));

    $response->assertForbidden();
});

it('paginates activities', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    Activity::factory()
        ->forWorkspace($workspace)
        ->count(25)
        ->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(route('activities.index', ['workspace' => $workspace, 'per_page' => 10]));

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.per_page', 10);
});

it('shows system action when actor is null', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    Activity::factory()
        ->forWorkspace($workspace)
        ->system()
        ->create();

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(route('activities.index', $workspace));

    $response->assertOk()
        ->assertJsonPath('data.0.is_system_action', true)
        ->assertJsonPath('data.0.actor', null);
});

it('returns activities in descending order by created_at', function (): void {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->teamMembers()->create([
        'user_id' => $owner->id,
        'team_id' => $workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $oldActivity = Activity::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => now()->subHour()]);

    $newActivity = Activity::factory()
        ->forWorkspace($workspace)
        ->create(['created_at' => now()]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson(route('activities.index', $workspace));

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids[0])->toBe($newActivity->id)
        ->and($ids[1])->toBe($oldActivity->id);
});

it('requires authentication to view activities', function (): void {
    $workspace = Workspace::factory()->create();

    $response = $this->getJson(route('activities.index', $workspace));

    $response->assertUnauthorized();
});
