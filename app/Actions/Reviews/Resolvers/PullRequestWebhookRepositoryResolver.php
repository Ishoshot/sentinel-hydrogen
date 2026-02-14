<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Resolvers;

use App\Models\Installation;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

final readonly class PullRequestWebhookRepositoryResolver
{
    /**
     * Resolve the repository targeted by a pull request webhook payload.
     *
     * @param  array{installation_id: int, repository_id: int}  $payload
     * @param  array<string, mixed>  $logContext
     */
    public function resolve(array $payload, array $logContext): ?Repository
    {
        $installation = Installation::query()->where('installation_id', $payload['installation_id'])->first();

        if ($installation === null) {
            Log::warning('Installation not found for pull request webhook', $logContext);

            return null;
        }

        $repository = Repository::query()
            ->where('installation_id', $installation->id)
            ->where('github_id', $payload['repository_id'])
            ->first();

        if ($repository === null) {
            Log::warning('Repository not found for pull request webhook', array_merge($logContext, [
                'github_repository_id' => $payload['repository_id'],
            ]));
        }

        return $repository;
    }
}
