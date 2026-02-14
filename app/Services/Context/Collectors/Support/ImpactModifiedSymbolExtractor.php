<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\Context\ContextBag;

/**
 * Extracts modified symbols by intersecting semantic ranges and patch line edits.
 */
final class ImpactModifiedSymbolExtractor
{
    /**
     * Extract modified symbols from semantic analysis.
     *
     * @return array<int, array{name: string, type: string, file: string}>
     */
    public function extractModifiedSymbols(ContextBag $bag): array
    {
        $modifiedSymbols = [];

        foreach ($bag->semantics as $filename => $semanticData) {
            $fileEntry = $this->findFileEntry($bag->files, $filename);
            if ($fileEntry === null) {
                continue;
            }

            $patch = $fileEntry['patch'];
            if (! is_string($patch)) {
                continue;
            }

            $modifiedLines = $this->parseModifiedLines($patch);

            $functions = $semanticData['functions'] ?? [];
            if (! is_array($functions)) {
                $functions = [];
            }

            foreach ($functions as $function) {
                if (! is_array($function)) {
                    continue;
                }

                $functionName = $function['name'] ?? null;
                if (! is_string($functionName)) {
                    continue;
                }

                if ($functionName === '') {
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
                if (! is_string($className)) {
                    continue;
                }

                if ($className === '') {
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
                    if (! is_string($methodName)) {
                        continue;
                    }

                    if ($methodName === '') {
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
        }

        return $modifiedSymbols;
    }

    /**
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}|null
     */
    private function findFileEntry(array $files, string $filename): ?array
    {
        foreach ($files as $file) {
            if ($file['filename'] === $filename) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function parseModifiedLines(string $patch): array
    {
        $modifiedLines = [];
        $currentLine = 0;

        foreach (explode("\n", $patch) as $line) {
            if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/', $line, $matches)) {
                $currentLine = (int) $matches[1];

                continue;
            }

            if ($line === '') {
                continue;
            }

            $firstChar = $line[0] ?? '';

            if ($firstChar === '+' && ! str_starts_with($line, '+++')) {
                $modifiedLines[] = $currentLine;
                $currentLine++;
            } elseif ($firstChar === '-' && ! str_starts_with($line, '---')) {
                continue;
            } elseif ($firstChar === ' ') {
                $currentLine++;
            }
        }

        return $modifiedLines;
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
