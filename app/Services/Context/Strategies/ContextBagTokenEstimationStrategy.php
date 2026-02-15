<?php

declare(strict_types=1);

namespace App\Services\Context\Strategies;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;

/**
 * Estimates the total token count for a context bag's contents.
 */
final readonly class ContextBagTokenEstimationStrategy
{
    /**
     * Estimate the total token count across all context bag sections.
     */
    public function estimate(ContextBag $bag, TokenCounter $tokenCounter, TokenCounterContext $context): int
    {
        $totalTokens = 0;

        // Pull request metadata
        $totalTokens += $tokenCounter->countTextTokens(json_encode($bag->pullRequest) ?: '', $context);

        // Files with patches (most significant)
        foreach ($bag->files as $file) {
            $totalTokens += $tokenCounter->countTextTokens($file['filename'], $context);
            $totalTokens += $tokenCounter->countTextTokens($file['patch'] ?? '', $context);
        }

        // Metrics
        $totalTokens += $tokenCounter->countTextTokens(json_encode($bag->metrics) ?: '', $context);

        // Linked issues
        foreach ($bag->linkedIssues as $issue) {
            $totalTokens += $tokenCounter->countTextTokens($issue['title'], $context);
            $totalTokens += $tokenCounter->countTextTokens($issue['body'] ?? '', $context);
            foreach ($issue['comments'] as $comment) {
                $totalTokens += $tokenCounter->countTextTokens($comment['body'], $context);
            }
        }

        // PR comments
        foreach ($bag->prComments as $comment) {
            $totalTokens += $tokenCounter->countTextTokens($comment['body'], $context);
        }

        // Repository context
        $totalTokens += $tokenCounter->countTextTokens($bag->repositoryContext['readme'] ?? '', $context);
        $totalTokens += $tokenCounter->countTextTokens($bag->repositoryContext['contributing'] ?? '', $context);

        // Review history
        foreach ($bag->reviewHistory as $review) {
            $totalTokens += $tokenCounter->countTextTokens($review['summary'], $context);
        }

        // Guidelines
        foreach ($bag->guidelines as $guideline) {
            $totalTokens += $tokenCounter->countTextTokens($guideline['content'], $context);
        }

        // File contents
        foreach ($bag->fileContents as $content) {
            $totalTokens += $tokenCounter->countTextTokens($content, $context);
        }

        // Semantic analysis data
        foreach ($bag->semantics as $data) {
            $totalTokens += $tokenCounter->countTextTokens(json_encode($data) ?: '', $context);
        }

        // Project context
        $totalTokens += $tokenCounter->countTextTokens(json_encode($bag->projectContext) ?: '', $context);

        // Impacted files
        foreach ($bag->impactedFiles as $impactedFile) {
            $totalTokens += $tokenCounter->countTextTokens($impactedFile['content'], $context);
        }

        return $totalTokens;
    }
}
