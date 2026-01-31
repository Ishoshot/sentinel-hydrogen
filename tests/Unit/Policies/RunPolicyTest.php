<?php

declare(strict_types=1);

use App\Enums\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\RunPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new RunPolicy;
    $this->provider = Provider::where('type', ProviderType::GitHub)->first();
});

it('allows workspace members to view any runs', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

    expect($this->policy->viewAny($user, $workspace))->toBeTrue();
});

it('denies non-members from viewing any runs', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    expect($this->policy->viewAny($user, $workspace))->toBeFalse();
});

it('allows workspace members to view a run', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    expect($this->policy->view($user, $run))->toBeTrue();
});

it('denies non-members from viewing a run', function (): void {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();
    $connection = Connection::factory()->forProvider($this->provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    expect($this->policy->view($user, $run))->toBeFalse();
});
