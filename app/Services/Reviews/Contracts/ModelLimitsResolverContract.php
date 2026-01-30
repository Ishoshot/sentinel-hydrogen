<?php

declare(strict_types=1);

namespace App\Services\Reviews\Contracts;

use App\Enums\AI\AiProvider;
use App\Services\Reviews\ValueObjects\ModelLimits;

/**
 * Contract for resolving AI model context window and output limits.
 */
interface ModelLimitsResolverContract
{
    /**
     * Resolve limits for a provider model.
     */
    public function resolve(AiProvider $provider, string $identifier): ModelLimits;
}
