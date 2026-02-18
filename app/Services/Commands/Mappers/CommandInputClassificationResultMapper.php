<?php

declare(strict_types=1);

namespace App\Services\Commands\Mappers;

use App\Services\Commands\ValueObjects\CommandInputClassificationResult;

final class CommandInputClassificationResultMapper
{
    /**
     * Normalize a structured payload into a classification value object.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function map(?array $payload): CommandInputClassificationResult
    {
        if (! is_array($payload)) {
            return CommandInputClassificationResult::allow('Classifier returned no structured payload.');
        }

        return CommandInputClassificationResult::fromArray($payload);
    }
}
