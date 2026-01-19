<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BriefingSubscription;
use App\Models\User;
use App\Models\Workspace;

final class BriefingSubscriptionPolicy
{
    /**
     * Determine whether the user can view any subscriptions for a workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can view the subscription.
     */
    public function view(User $user, BriefingSubscription $subscription): bool
    {
        // Users can only view their own subscriptions
        return $subscription->user_id === $user->id;
    }

    /**
     * Determine whether the user can create a subscription.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    /**
     * Determine whether the user can update the subscription.
     */
    public function update(User $user, BriefingSubscription $subscription): bool
    {
        // Users can only update their own subscriptions
        return $subscription->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the subscription.
     */
    public function delete(User $user, BriefingSubscription $subscription): bool
    {
        // Users can only delete their own subscriptions
        return $subscription->user_id === $user->id;
    }
}
