<?php

declare(strict_types=1);

use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\BriefingSharePolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new BriefingSharePolicy;
});

describe('create', function (): void {
    it('allows owners to create share for workspace generation', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();

        expect($this->policy->create($user, $workspace, $generation))->toBeTrue();
    });

    it('allows admins to create share for workspace generation', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();

        expect($this->policy->create($user, $workspace, $generation))->toBeTrue();
    });

    it('denies members from creating share', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();

        expect($this->policy->create($user, $workspace, $generation))->toBeFalse();
    });

    it('denies non-members from creating share', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();

        expect($this->policy->create($user, $workspace, $generation))->toBeFalse();
    });

    it('denies creating share for generation from different workspace', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($otherWorkspace)->create();

        expect($this->policy->create($user, $workspace, $generation))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('allows owners to delete share', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->owner()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();
        $share = BriefingShare::factory()->forGeneration($generation)->create();

        expect($this->policy->delete($user, $share))->toBeTrue();
    });

    it('allows admins to delete share', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->admin()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();
        $share = BriefingShare::factory()->forGeneration($generation)->create();

        expect($this->policy->delete($user, $share))->toBeTrue();
    });

    it('denies members from deleting share', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->member()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();
        $share = BriefingShare::factory()->forGeneration($generation)->create();

        expect($this->policy->delete($user, $share))->toBeFalse();
    });

    it('denies non-members from deleting share', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $generation = BriefingGeneration::factory()->forWorkspace($workspace)->create();
        $share = BriefingShare::factory()->forGeneration($generation)->create();

        expect($this->policy->delete($user, $share))->toBeFalse();
    });

    it('denies delete when share has no generation', function (): void {
        $user = User::factory()->create();
        $share = BriefingShare::factory()->create();
        $share->setRelation('generation', null);

        expect($this->policy->delete($user, $share))->toBeFalse();
    });

    it('denies delete when generation has no workspace', function (): void {
        $user = User::factory()->create();
        $generation = BriefingGeneration::factory()->create();
        $generation->setRelation('workspace', null);
        $share = BriefingShare::factory()->forGeneration($generation)->create();
        $share->setRelation('generation', $generation);

        expect($this->policy->delete($user, $share))->toBeFalse();
    });
});
