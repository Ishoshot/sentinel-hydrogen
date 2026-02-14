<?php

declare(strict_types=1);

namespace App\Services\Briefings\Support;

final class BriefingParameterSchemaMessageBuilder
{
    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    public function build(string $field, array $definition): array
    {
        if (! isset($definition['description'])) {
            return [];
        }

        return [
            $field.'.required' => (string) $definition['description'],
        ];
    }
}
