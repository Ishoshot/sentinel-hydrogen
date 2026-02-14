<?php

declare(strict_types=1);

namespace App\Actions\Commands\Builders;

/**
 * Builds the metrics footer for command response comments.
 */
final readonly class FooterMetricsBuilder
{
    private const string POWERED_BY = 'Powered by [Sentinel](https://sentinelapp.dev)';

    /**
     * Build the footer with metrics.
     *
     * @param  array<string, mixed>  $metrics
     */
    public function build(array $metrics): string
    {
        $parts = [];

        if (isset($metrics['model'])) {
            $parts[] = sprintf('Model: `%s`', $metrics['model']);
        }

        if (isset($metrics['duration_ms']) && is_numeric($metrics['duration_ms'])) {
            $duration = number_format((float) $metrics['duration_ms'] / 1000, 1);
            $parts[] = sprintf('Time: %ss', $duration);
        }

        if ($parts === []) {
            return "\n\n---\n*".self::POWERED_BY.'*';
        }

        return "\n\n---\n<sub>".implode(' | ', $parts).' | '.self::POWERED_BY.'</sub>';
    }
}
