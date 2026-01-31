<?php

declare(strict_types=1);

use App\Actions\Activities\ListWorkspaceActivities;
use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;

it('lists activities for workspace', function (): void {
    $workspace = Workspace::factory()->create();
    Activity::factory()->count(5)->forWorkspace($workspace)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace);

    expect($result)->toHaveCount(5);
});

it('only lists activities for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    Activity::factory()->count(3)->forWorkspace($workspace1)->create();
    Activity::factory()->count(5)->forWorkspace($workspace2)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace1);

    expect($result)->toHaveCount(3);
});

it('orders activities by created_at descending', function (): void {
    $workspace = Workspace::factory()->create();

    Activity::factory()->forWorkspace($workspace)->create(['created_at' => now()->subHours(2)]);
    Activity::factory()->forWorkspace($workspace)->create(['created_at' => now()]);
    Activity::factory()->forWorkspace($workspace)->create(['created_at' => now()->subHour()]);

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace);

    $dates = $result->pluck('created_at')->toArray();

    expect($dates[0]->isAfter($dates[1]))->toBeTrue();
    expect($dates[1]->isAfter($dates[2]))->toBeTrue();
});

it('filters by activity type', function (): void {
    $workspace = Workspace::factory()->create();

    Activity::factory()->forWorkspace($workspace)->type(ActivityType::WorkspaceCreated)->create();
    Activity::factory()->forWorkspace($workspace)->type(ActivityType::MemberInvited)->create();
    Activity::factory()->forWorkspace($workspace)->type(ActivityType::MemberInvited)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace, type: 'member.invited');

    expect($result)->toHaveCount(2);
});

it('ignores invalid type filter', function (): void {
    $workspace = Workspace::factory()->create();
    Activity::factory()->count(3)->forWorkspace($workspace)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace, type: 'invalid.type');

    expect($result)->toHaveCount(3);
});

it('filters by category', function (): void {
    $workspace = Workspace::factory()->create();

    Activity::factory()->forWorkspace($workspace)->type(ActivityType::MemberInvited)->create();
    Activity::factory()->forWorkspace($workspace)->type(ActivityType::MemberJoined)->create();
    Activity::factory()->forWorkspace($workspace)->type(ActivityType::WorkspaceCreated)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace, category: 'member');

    expect($result)->toHaveCount(2);
});

it('ignores invalid category filter', function (): void {
    $workspace = Workspace::factory()->create();
    Activity::factory()->count(3)->forWorkspace($workspace)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace, category: 'invalid-category');

    expect($result)->toHaveCount(3);
});

it('respects perPage parameter', function (): void {
    $workspace = Workspace::factory()->create();
    Activity::factory()->count(15)->forWorkspace($workspace)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace, perPage: 5);

    expect($result->count())->toBe(5);
    expect($result->total())->toBe(15);
});

it('eager loads actor relationship', function (): void {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    Activity::factory()->forWorkspace($workspace)->byActor($user)->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace);

    expect($result->first()->relationLoaded('actor'))->toBeTrue();
    expect($result->first()->actor->id)->toBe($user->id);
});

it('handles activities without actor', function (): void {
    $workspace = Workspace::factory()->create();
    Activity::factory()->forWorkspace($workspace)->system()->create();

    $action = new ListWorkspaceActivities;
    $result = $action->handle($workspace);

    expect($result->first()->actor)->toBeNull();
});

it('can filter by both type and category', function (): void {
    $workspace = Workspace::factory()->create();

    Activity::factory()->forWorkspace($workspace)->type(ActivityType::MemberInvited)->create();
    Activity::factory()->forWorkspace($workspace)->type(ActivityType::MemberJoined)->create();
    Activity::factory()->forWorkspace($workspace)->type(ActivityType::WorkspaceCreated)->create();

    $action = new ListWorkspaceActivities;
    // Type filter takes precedence
    $result = $action->handle($workspace, type: 'member.invited', category: 'member');

    expect($result)->toHaveCount(1);
});
