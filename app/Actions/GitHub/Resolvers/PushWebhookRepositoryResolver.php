<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Support;

use App\Models\Installation;
use App\Models\Repository;

final class PushWebhookRepositoryResolver
{
    /**
     * Resolve installation and repository records for a push webhook.
     */
    public function resolve(int $installationId, int $repositoryId): PushWebhookRepositoryResolution
    {
        $installation = Installation::query()->where('installation_id', $installationId)->first();

        if (! $installation instanceof Installation) {
            return new PushWebhookRepositoryResolution(
                installation: null,
                repository: null,
            );
        }

        $repository = Repository::query()
            ->where('installation_id', $installation->id)
            ->where('github_id', $repositoryId)
            ->first();

        return new PushWebhookRepositoryResolution(
            installation: $installation,
            repository: $repository instanceof Repository ? $repository : null,
        );
    }
}
