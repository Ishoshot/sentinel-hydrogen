<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Extracts semantic symbols that overlap modified patch lines.
 */
final readonly class ImpactSemanticSymbolExtractor
{
    /**
     * @param  array<string, mixed>  $semanticData
     * @param  array<int, int>  $modifiedLines
     * @return array<int, array{name: string, type: string, file: string}>
     */
    public function extract(string $filename, array $semanticData, array $modifiedLines): array
    {
        $modifiedSymbols = [];

        $functions = $semanticData['functions'] ?? [];
        if (! is_array($functions)) {
            $functions = [];
        }

        foreach ($functions as $function) {
            if (! is_array($function)) {
                continue;
            }

            $functionName = $function['name'] ?? null;
            if (! is_string($functionName) || $functionName === '') {
                continue;
            }

            if ($this->symbolOverlapsLines($function, $modifiedLines)) {
                $modifiedSymbols[] = [
                    'name' => $functionName,
                    'type' => 'function',
                    'file' => $filename,
                ];
            }
        }

        $classes = $semanticData['classes'] ?? [];
        if (! is_array($classes)) {
            $classes = [];
        }

        foreach ($classes as $class) {
            if (! is_array($class)) {
                continue;
            }

            $className = $class['name'] ?? null;
            if (! is_string($className) || $className === '') {
                continue;
            }

            if ($this->symbolOverlapsLines($class, $modifiedLines)) {
                $modifiedSymbols[] = [
                    'name' => $className,
                    'type' => 'class',
                    'file' => $filename,
                ];
            }

            $methods = $class['methods'] ?? [];
            if (! is_array($methods)) {
                $methods = [];
            }

            foreach ($methods as $method) {
                if (! is_array($method)) {
                    continue;
                }

                $methodName = $method['name'] ?? null;
                if (! is_string($methodName) || $methodName === '') {
                    continue;
                }

                if ($this->symbolOverlapsLines($method, $modifiedLines)) {
                    $modifiedSymbols[] = [
                        'name' => $methodName,
                        'type' => 'method',
                        'file' => $filename,
                    ];
                }
            }
        }

        return $modifiedSymbols;
    }

    /**
     * @param  array<int|string, mixed>  $symbol
     * @param  array<int, int>  $modifiedLines
     */
    private function symbolOverlapsLines(array $symbol, array $modifiedLines): bool
    {
        $lineStart = $symbol['line_start'] ?? null;
        $lineEnd = $symbol['line_end'] ?? $lineStart;

        if (! is_int($lineStart) || ! is_int($lineEnd)) {
            return false;
        }

        return array_any($modifiedLines, fn (int $line): bool => $line >= $lineStart && $line <= $lineEnd);
    }
}
