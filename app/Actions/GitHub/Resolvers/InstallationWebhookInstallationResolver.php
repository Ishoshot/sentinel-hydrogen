<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Resolvers;

use App\Models\Installation;

final class InstallationWebhookInstallationResolver
{
    /**
     * Resolve installation by GitHub installation ID.
     */
    public function resolve(int $installationId): ?Installation
    {
        $installation = Installation::query()->where('installation_id', $installationId)->first();

        return $installation instanceof Installation ? $installation : null;
    }
}
