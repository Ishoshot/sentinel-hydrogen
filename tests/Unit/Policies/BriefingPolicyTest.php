<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\BriefingPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new BriefingPolicy;
});

describe('viewAny', function (): void {
    it('allows workspace members to view any briefings', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

        expect($this->policy->viewAny($user, $workspace))->toBeTrue();
    });

    it('denies non-members from viewing any briefings', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        expect($this->policy->viewAny($user, $workspace))->toBeFalse();
    });
});

describe('view', function (): void {
    it('allows workspace members to view briefing', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();
        $briefing = Briefing::factory()->create();

        expect($this->policy->view($user, $briefing, $workspace))->toBeTrue();
    });

    it('denies non-members from viewing briefing', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $briefing = Briefing::factory()->create();

        expect($this->policy->view($user, $briefing, $workspace))->toBeFalse();
    });
});
