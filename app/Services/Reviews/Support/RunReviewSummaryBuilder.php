<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

use App\Models\Run;
use App\Services\SentinelMessageService;

/**
 * Builds markdown summary content for posted review annotations.
 */
final readonly class RunReviewSummaryBuilder
{
    /**
     * Create a new instance.
     */
    public function __construct(private SentinelMessageService $messageService) {}

    /**
     * Build a markdown summary for the review result.
     */
    public function build(Run $run): string
    {
        $metadata = $run->metadata ?? [];
        $summary = is_array($metadata['review_summary'] ?? null) ? $metadata['review_summary'] : [];

        $overview = is_string($summary['overview'] ?? null) ? $summary['overview'] : 'Review completed.';
        $riskLevel = is_string($summary['risk_level'] ?? null) ? $summary['risk_level'] : 'low';
        $recommendations = is_array($summary['recommendations'] ?? null) ? $summary['recommendations'] : [];

        $body = "## Sentinel Review Summary\n\n";
        $body .= '**Risk Level:** '.ucfirst($riskLevel)."\n\n";
        $body .= $overview."\n";

        $findingsCount = $run->findings()->count();
        if ($findingsCount > 0) {
            $body .= "\n**Findings:** {$findingsCount} issue(s) identified.\n";
        }

        if ($recommendations !== []) {
            $body .= "\n### Recommendations\n\n";
            foreach ($recommendations as $recommendation) {
                if (is_string($recommendation)) {
                    $body .= sprintf('- %s%s', $recommendation, PHP_EOL);
                }
            }
        }

        return $body.$this->messageService->buildReviewSignOff($this->buildRunUrl($run));
    }

    /**
     * Build the frontend run URL used in review sign-off content.
     */
    private function buildRunUrl(Run $run): string
    {
        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return sprintf(
            '%s/workspaces/%s/runs/%s',
            mb_rtrim($frontendUrl, '/'),
            $run->workspace?->slug,
            $run->id
        );
    }
}
