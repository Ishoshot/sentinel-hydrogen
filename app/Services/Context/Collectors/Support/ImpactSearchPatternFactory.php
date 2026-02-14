<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Builds code-search patterns for modified symbol references.
 */
final readonly class ImpactSearchPatternFactory
{
    /**
     * @param  array{name: string, type: string, file: string}  $symbol
     * @return array<string, string>
     */
    public function build(array $symbol): array
    {
        $name = $symbol['name'];
        $type = $symbol['type'];

        return match ($type) {
            'function' => [
                $name.'(' => 'function_call',
            ],
            'class' => [
                'new '.$name => 'class_instantiation',
                'extends '.$name => 'extends',
                'implements '.$name => 'implements',
            ],
            'method' => [
                sprintf('->%s(', $name) => 'method_call',
                sprintf('::%s(', $name) => 'method_call',
            ],
            default => [
                $name => 'reference',
            ],
        };
    }
}
