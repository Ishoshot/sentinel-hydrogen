<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

use App\Events\GitHub\ConfigPullRequestCreated;
use App\Models\Repository;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\ValueObjects\ConfigPullRequestResult;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;

final readonly class CreateConfigPullRequest
{
    /**
     * Handles branch preparation for the Sentinel config pull request flow.
     */
    private PrepareConfigBranch $prepareConfigBranch;

    /**
     * Create a new action instance.
     */
    public function __construct(
        GitHubApiServiceContract $gitHubApiService,
        ?PrepareConfigBranch $prepareConfigBranch = null,
    ) {
        $this->prepareConfigBranch = $prepareConfigBranch ?? new PrepareConfigBranch($gitHubApiService);
    }

    /**
     * Prepare a branch with the Sentinel configuration file and return a compare URL.
     */
    public function handle(Repository $repository): ConfigPullRequestResult
    {
        $repository->loadMissing('installation');

        $installation = $repository->installation;

        if ($installation === null) {
            return ConfigPullRequestResult::failed('Repository has no associated installation');
        }

        $owner = $repository->owner;
        $repo = $repository->name;
        $installationId = $installation->installation_id;
        $defaultBranch = $repository->default_branch ?? 'main';

        Log::info('Preparing config branch for repository', [
            'repository_id' => $repository->id,
            'full_name' => $repository->full_name,
        ]);

        try {
            $preparation = $this->prepareConfigBranch->handle(
                installationId: $installationId,
                owner: $owner,
                repo: $repo,
                defaultBranch: $defaultBranch,
            );

            $result = $preparation['result'];
            $shouldDispatchEvent = $preparation['should_dispatch_event'];

            if ($result->wasSkipped()) {
                Log::info('Config file already exists', ['repository' => $repository->full_name]);

                return $result;
            }

            if ($result->isReady() && is_string($result->compareUrl)) {
                Log::info('Config branch ready for PR', [
                    'repository' => $repository->full_name,
                    'compare_url' => $result->compareUrl,
                ]);

                if ($shouldDispatchEvent) {
                    ConfigPullRequestCreated::dispatch(
                        $repository->workspace_id,
                        $repository->id,
                        $repository->full_name,
                        $result->compareUrl
                    );
                }
            }

            return $result;
        } catch (RuntimeException $runtimeException) {
            $message = $runtimeException->getMessage();

            if (
                str_contains($message, '403')
                || str_contains(mb_strtolower($message), 'permission')
                || str_contains(mb_strtolower($message), 'resource not accessible')
            ) {
                Log::warning('Insufficient permissions to create config branch', [
                    'repository' => $repository->full_name,
                    'error' => $message,
                ]);

                return ConfigPullRequestResult::failed('Insufficient permissions. Please check your GitHub App permissions.');
            }

            Log::error('Failed to create config branch', [
                'repository' => $repository->full_name,
                'error' => $message,
            ]);

            return ConfigPullRequestResult::failed($message);
        }
    }
}
