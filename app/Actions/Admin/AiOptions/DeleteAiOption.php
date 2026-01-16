<?php

declare(strict_types=1);

namespace App\Actions\Admin\AiOptions;

use App\Models\AiOption;
use InvalidArgumentException;

/**
 * Delete an AI option.
 */
final readonly class DeleteAiOption
{
    /**
     * Delete an AI option.
     *
     * @throws InvalidArgumentException If the AI option is in use by provider keys.
     */
    public function handle(AiOption $aiOption): void
    {
        // Check if any provider keys are using this AI option
        $usageCount = $aiOption->providerKeys()->count();

        if ($usageCount > 0) {
            throw new InvalidArgumentException(
                "Cannot delete this AI model as it is currently in use by {$usageCount} provider key(s)."
            );
        }

        $aiOption->delete();
    }
}
