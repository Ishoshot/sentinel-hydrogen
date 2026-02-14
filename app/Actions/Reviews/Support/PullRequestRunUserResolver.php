<?php

declare(strict_types=1);

namespace App\Actions\Reviews\Support;

use App\Enums\Auth\OAuthProvider;
use App\Models\ProviderIdentity;

final readonly class PullRequestRunUserResolver
{
    /**
     * Find a Sentinel user ID by their GitHub login.
     */
    public function resolveUserId(string $githubLogin): ?int
    {
        $identity = ProviderIdentity::query()
            ->where('provider', OAuthProvider::GitHub)
            ->whereRaw('LOWER(nickname) = ?', [mb_strtolower($githubLogin)])
            ->first();

        return $identity?->user_id;
    }
}
