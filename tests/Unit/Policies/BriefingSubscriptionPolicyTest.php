<?php

declare(strict_types=1);

use App\Models\BriefingSubscription;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\BriefingSubscriptionPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new BriefingSubscriptionPolicy;
});

describe('viewAny', function (): void {
    it('allows workspace members to view any subscriptions', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

        expect($this->policy->viewAny($user, $workspace))->toBeTrue();
    });

    it('denies non-members from viewing any subscriptions', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        expect($this->policy->viewAny($user, $workspace))->toBeFalse();
    });
});

describe('view', function (): void {
    it('allows users to view their own subscription', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $subscription = BriefingSubscription::factory()
            ->forUser($user)
            ->forWorkspace($workspace)
            ->create();

        expect($this->policy->view($user, $subscription))->toBeTrue();
    });

    it('denies users from viewing other users subscriptions', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $subscription = BriefingSubscription::factory()
            ->forUser($otherUser)
            ->forWorkspace($workspace)
            ->create();

        expect($this->policy->view($user, $subscription))->toBeFalse();
    });
});

describe('create', function (): void {
    it('allows workspace members to create subscription', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        TeamMember::factory()->forUser($user)->forWorkspace($workspace)->create();

        expect($this->policy->create($user, $workspace))->toBeTrue();
    });

    it('denies non-members from creating subscription', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();

        expect($this->policy->create($user, $workspace))->toBeFalse();
    });
});

describe('update', function (): void {
    it('allows users to update their own subscription', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $subscription = BriefingSubscription::factory()
            ->forUser($user)
            ->forWorkspace($workspace)
            ->create();

        expect($this->policy->update($user, $subscription))->toBeTrue();
    });

    it('denies users from updating other users subscriptions', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $subscription = BriefingSubscription::factory()
            ->forUser($otherUser)
            ->forWorkspace($workspace)
            ->create();

        expect($this->policy->update($user, $subscription))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('allows users to delete their own subscription', function (): void {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $subscription = BriefingSubscription::factory()
            ->forUser($user)
            ->forWorkspace($workspace)
            ->create();

        expect($this->policy->delete($user, $subscription))->toBeTrue();
    });

    it('denies users from deleting other users subscriptions', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $subscription = BriefingSubscription::factory()
            ->forUser($otherUser)
            ->forWorkspace($workspace)
            ->create();

        expect($this->policy->delete($user, $subscription))->toBeFalse();
    });
});
