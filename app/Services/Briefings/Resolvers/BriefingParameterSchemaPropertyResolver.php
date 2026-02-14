<?php

declare(strict_types=1);

namespace App\Services\Briefings\Resolvers;

use RuntimeException;

final class BriefingParameterSchemaPropertyResolver
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<mixed, mixed>
     */
    public function resolve(array $schema): array
    {
        $properties = $schema['properties'] ?? null;

        if (! is_array($properties) || $properties === []) {
            throw new RuntimeException('Briefing parameter schema must define properties.');
        }

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    public function required(array $schema): array
    {
        /** @var array<int, string> $required */
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        return $required;
    }
}
