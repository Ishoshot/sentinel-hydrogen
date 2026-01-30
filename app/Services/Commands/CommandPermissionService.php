<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Enums\Auth\OAuthProvider;
use App\Models\ProviderIdentity;
use App\Models\Repository;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Commands\ValueObjects\CommandPermissionResult;
use App\Services\Plans\PlanLimitEnforcer;

/**
 * Service for validating command permissions.
 *
 * Checks workspace membership, repository access, plan limits, and BYOK requirements.
 */
final readonly class CommandPermissionService
{
    /**
     * Create a new CommandPermissionService instance.
     */
    public function __construct(
        private PlanLimitEnforcer $planLimitEnforcer,
    ) {}

    /**
     * Check if a GitHub user can execute commands on a repository.
     *
     * @param  string  $githubUsername  The GitHub username of the user
     * @param  string  $repositoryFullName  The full repository name (owner/repo)
     */
    public function checkPermission(string $githubUsername, string $repositoryFullName): CommandPermissionResult
    {
        // 1. Find the user by GitHub username
        $user = $this->findUserByGitHubUsername($githubUsername);

        if (! $user instanceof User) {
            return CommandPermissionResult::deny(
                'You must have a Sentinel account linked to your GitHub to use commands. Visit our website to sign up.',
                'user_not_found'
            );
        }

        // 2. Find the repository
        $repository = Repository::where('full_name', $repositoryFullName)->first();

        if ($repository === null) {
            return CommandPermissionResult::deny(
                'This repository is not connected to Sentinel. Ask a workspace admin to connect it.',
                'repository_not_found'
            );
        }

        // 3. Get the workspace
        $workspace = $repository->workspace;

        if ($workspace === null) {
            return CommandPermissionResult::deny(
                'This repository is not associated with a workspace.',
                'workspace_not_found'
            );
        }

        // 4. Check workspace membership
        if (! $user->belongsToWorkspace($workspace)) {
            return CommandPermissionResult::deny(
                'You are not a member of this workspace. Ask a workspace admin for an invitation.',
                'not_workspace_member'
            );
        }

        // 5. Check subscription status
        $subscriptionCheck = $this->planLimitEnforcer->ensureActiveSubscription($workspace);
        if (! $subscriptionCheck->allowed) {
            return CommandPermissionResult::deny(
                $subscriptionCheck->message ?? 'Subscription is not active.',
                'subscription_inactive'
            );
        }

        // 6. Check command limits
        $commandLimitCheck = $this->planLimitEnforcer->ensureCommandAllowed($workspace);
        if (! $commandLimitCheck->allowed) {
            return CommandPermissionResult::deny(
                $commandLimitCheck->message ?? 'Command limit reached.',
                'commands_limit'
            );
        }

        // 7. Check BYOK requirement (must have at least one AI provider key)
        if (! $this->hasProviderKeys($repository)) {
            return CommandPermissionResult::deny(
                'No AI provider keys configured. Add an API key (OpenAI or Anthropic) in your workspace settings to use commands.',
                'no_provider_keys'
            );
        }

        return CommandPermissionResult::allow($user, $workspace, $repository);
    }

    /**
     * Find a user by their GitHub username.
     */
    private function findUserByGitHubUsername(string $username): ?User
    {
        $providerIdentity = ProviderIdentity::where('provider', OAuthProvider::GitHub)
            ->where('nickname', $username)
            ->first();

        return $providerIdentity?->user;
    }

    /**
     * Check if the repository has any AI provider keys configured.
     */
    private function hasProviderKeys(Repository $repository): bool
    {
        return $repository->providerKeys()->exists();
    }
}
