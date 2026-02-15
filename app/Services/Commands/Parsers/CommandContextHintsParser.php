<?php

declare(strict_types=1);

namespace App\Services\Commands\Parsers;

final class CommandContextHintsParser
{
    /**
     * @return array{files?: array<string>, symbols?: array<string>, lines?: array<array{start: int, end: int|null}>}|null
     */
    public function normalize(mixed $contextHints): ?array
    {
        if (! is_array($contextHints)) {
            return null;
        }

        $normalized = [];

        if (isset($contextHints['files']) && is_array($contextHints['files'])) {
            $files = array_values(array_filter($contextHints['files'], is_string(...)));

            if ($files !== []) {
                $normalized['files'] = $files;
            }
        }

        if (isset($contextHints['symbols']) && is_array($contextHints['symbols'])) {
            $symbols = array_values(array_filter($contextHints['symbols'], is_string(...)));

            if ($symbols !== []) {
                $normalized['symbols'] = $symbols;
            }
        }

        if (isset($contextHints['lines']) && is_array($contextHints['lines'])) {
            $lines = [];

            foreach ($contextHints['lines'] as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $start = $line['start'] ?? null;
                $end = $line['end'] ?? null;

                if (! is_int($start)) {
                    continue;
                }

                $lines[] = [
                    'start' => $start,
                    'end' => is_int($end) ? $end : null,
                ];
            }

            if ($lines !== []) {
                $normalized['lines'] = $lines;
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}
