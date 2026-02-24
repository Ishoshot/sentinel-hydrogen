<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Resolvers;

use App\Models\Installation;
use App\Models\Repository;
use App\Services\GitHub\ValueObjects\PullRequestWebhookPayload;
use Illuminate\Support\Facades\Log;

final readonly class PullRequestWebhookRepositoryResolver
{
    /**
     * Resolve the repository targeted by a pull request webhook payload.
     *
     * @param  array<string, mixed>  $logContext
     */
    public function resolve(PullRequestWebhookPayload $payload, array $logContext): ?Repository
    {
        $installation = Installation::query()->where('installation_id', $payload->installationId)->first();

        if ($installation === null) {
            Log::warning('Installation not found for pull request webhook', $logContext);

            return null;
        }

        $repository = Repository::query()
            ->where('installation_id', $installation->id)
            ->where('github_id', $payload->repositoryId)
            ->first();

        if ($repository === null) {
            Log::warning('Repository not found for pull request webhook', array_merge($logContext, [
                'github_repository_id' => $payload->repositoryId,
            ]));
        }

        return $repository;
    }
}
