<?php

declare(strict_types=1);

namespace App\Services\Reviews\Builders;

use App\Models\Run;

final class RunAcknowledgmentStatusBodyBuilder
{
    /**
     * Build the completed-state acknowledgment body.
     */
    public function forCompleted(Run $run, int $findingsCount): string
    {
        $summary = $findingsCount > 0
            ? sprintf('Identified %d finding%s for this run.', $findingsCount, $findingsCount === 1 ? '' : 's')
            : 'No actionable findings were identified for this run.';

        $body = <<<MARKDOWN
        ✅ **Sentinel Review Completed**

        {$summary}

        **Run ID:** `{$run->id}`
        MARKDOWN;

        return $this->appendRunLink($body, $run);
    }

    /**
     * Build the skipped-state acknowledgment body.
     */
    public function forSkipped(Run $run, string $reason): string
    {
        $body = <<<MARKDOWN
        ⚠️ **Sentinel Review Skipped**

        {$reason}

        **Run ID:** `{$run->id}`
        MARKDOWN;

        return $this->appendRunLink($body, $run);
    }

    /**
     * Build the failed-state acknowledgment body.
     */
    public function forFailed(Run $run, string $errorType): string
    {
        $body = <<<MARKDOWN
        ❌ **Sentinel Review Failed**

        Sentinel encountered an error while processing this run.

        **Error Type:** `{$errorType}`
        **Run ID:** `{$run->id}`
        MARKDOWN;

        return $this->appendRunLink($body, $run);
    }

    /**
     * Append a frontend run link when available.
     */
    private function appendRunLink(string $body, Run $run): string
    {
        $runUrl = $this->buildRunUrl($run);

        if ($runUrl === null) {
            return $body;
        }

        return $body."\n\n📊 [View full analysis]({$runUrl})";
    }

    /**
     * Build the frontend run URL.
     */
    private function buildRunUrl(Run $run): ?string
    {
        $workspaceSlug = $run->workspace?->slug;

        if (! is_string($workspaceSlug) || $workspaceSlug === '') {
            return null;
        }

        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return sprintf(
            '%s/workspaces/%s/runs/%d',
            mb_rtrim($frontendUrl, '/'),
            $workspaceSlug,
            $run->id
        );
    }
}
