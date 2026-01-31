<?php

declare(strict_types=1);

use App\Models\BriefingGeneration;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\BriefingGenerationPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new BriefingGenerationPolicy;
});

describe('viewAny', function (): void {
    it('allows workspace members to view any generations', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

        expect($this->policy->viewAny($user, $workspace))->toBeTrue();
    });

    it('denies non-members from viewing any generations', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        expect($this->policy->viewAny($user, $workspace))->toBeFalse();
    });
});

describe('view', function (): void {
    it('allows workspace members to view generation', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();

        expect($this->policy->view($user, $generation))->toBeTrue();
    });

    it('denies non-members from viewing generation', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();

        expect($this->policy->view($user, $generation))->toBeFalse();
    });

    it('denies view when generation has no workspace', function (): void {
        $user = User::factory()->create();
        $generation = BriefingGeneration::factory()->create();

        // Manually null out workspace relationship
        $generation->setRelation('workspace', null);

        expect($this->policy->view($user, $generation))->toBeFalse();
    });
});

describe('create', function (): void {
    it('allows workspace members to create generation', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

        expect($this->policy->create($user, $workspace))->toBeTrue();
    });

    it('denies non-members from creating generation', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        expect($this->policy->create($user, $workspace))->toBeFalse();
    });
});
