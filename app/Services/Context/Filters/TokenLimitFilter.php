<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use App\Services\Context\Contracts\TokenCounter;
use App\Services\Context\TokenCounting\TokenCounterContext;
use Illuminate\Support\Facades\Log;

/**
 * Limits context to fit within LLM token constraints.
 *
 * The context budget is calculated dynamically by PrismReviewEngine based on the
 * model's context window: budget = contextWindow - systemPrompt - outputBudget - safety.
 * For example, Claude Sonnet (200K context) yields ~188K budget, while GPT-4 (128K)
 * yields ~115K. Section budgets scale proportionally with the total budget.
 *
 * Truncates context starting with lowest priority content (repository context,
 * review history) and working up to preserve the most important content (code diffs).
 */
final class TokenLimitFilter implements ContextFilter
{
    /**
     * Fallback maximum context tokens when no budget is provided via metadata.
     * This is only used when PrismReviewEngine doesn't set context_token_budget.
     */
    private const int DEFAULT_MAX_CONTEXT_TOKENS = 80000;

    /**
     * Minimum context budget to avoid over-truncating small prompts.
     */
    private const int MIN_CONTEXT_TOKENS = 8000;

    /**
     * Minimum token budget for a section.
     */
    private const int MIN_SECTION_TOKENS = 500;

    /**
     * Section budget ratios (percentage of total context budget).
     * These define how the context budget is distributed across sections.
     * Total: 45+12+10+8+6+5+5+5+4+3 = 103% (intentionally over 100% since not all sections are populated).
     */
    private const float RATIO_FILES_TOTAL = 0.45;

    private const float RATIO_FILES_PER = 0.08;

    private const float RATIO_IMPACTED_FILES = 0.12;

    private const float RATIO_IMPACTED_FILE_SINGLE = 0.25;

    private const float RATIO_ISSUES = 0.08;

    private const float RATIO_COMMENTS = 0.04;

    private const float RATIO_GUIDELINES = 0.06;

    private const float RATIO_REPOSITORY_CONTEXT = 0.05;

    private const float RATIO_REVIEW_HISTORY = 0.05;

    private const float RATIO_PROJECT_CONTEXT = 0.03;

    private const float RATIO_FILE_CONTENTS = 0.10;

    private const float RATIO_FILE_CONTENTS_SINGLE = 0.20;

    private const float RATIO_SEMANTICS = 0.05;

    /**
     * Maximum caps to prevent extreme allocations on very large context models.
     * These are safety bounds, not typical operating limits.
     */
    private const int MAX_FILES_TOTAL = 150000;

    private const int MAX_FILES_PER = 20000;

    private const int MAX_IMPACTED_FILES = 40000;

    private const int MAX_FILE_CONTENTS = 30000;

    private const int MAX_SEMANTICS = 15000;

    /**
     * Token counting context derived from the current request metadata.
     */
    private TokenCounterContext $tokenCounterContext;

    /**
     * Create a new filter instance.
     */
    public function __construct(
        private readonly TokenCounter $tokenCounter,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'token_limit';
    }

    /**
     * {@inheritdoc}
     */
    public function order(): int
    {
        return 100; // Run last to truncate after all other filters
    }

    /**
     * {@inheritdoc}
     */
    public function filter(ContextBag $bag): void
    {
        $this->tokenCounterContext = TokenCounterContext::fromMetadata($bag->metadata);
        $initialTokens = $bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext);
        $maxContextTokens = $this->resolveMaxContextTokens($bag);
        $budgets = $this->resolveBudgets($maxContextTokens);

        // Truncate individual file patches that are too large
        $bag->files = $this->truncateFiles(
            $bag->files,
            $budgets['files_per'],
            $budgets['files_total']
        );

        // Truncate impacted files if needed
        $bag->impactedFiles = $this->truncateImpactedFiles($bag->impactedFiles, $budgets['impacted_files']);

        // Truncate full file contents if needed
        $bag->fileContents = $this->truncateFileContents($bag->fileContents, $budgets['file_contents']);

        // Truncate semantics if needed
        $bag->semantics = $this->truncateSemantics($bag->semantics, $budgets['semantics']);

        // Truncate linked issues if needed
        $bag->linkedIssues = $this->truncateLinkedIssues($bag->linkedIssues, $budgets['issues']);

        // Truncate PR comments if needed
        $bag->prComments = $this->truncatePrComments($bag->prComments, $budgets['comments']);

        // Truncate guidelines if needed
        $bag->guidelines = $this->truncateGuidelines($bag->guidelines, $budgets['guidelines']);

        // Truncate repository context if needed
        $bag->repositoryContext = $this->truncateRepositoryContext($bag->repositoryContext, $budgets['repository_context']);

        // Truncate review history if needed
        $bag->reviewHistory = $this->truncateReviewHistory($bag->reviewHistory, $budgets['review_history']);

        // Truncate project context if needed
        $bag->projectContext = $this->truncateProjectContext($bag->projectContext, $budgets['project_context']);

        // If still over limit, progressively remove lower priority content
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $this->progressiveTruncation($bag, $maxContextTokens);
        }

        $finalTokens = $bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext);

        if ($initialTokens !== $finalTokens) {
            Log::info('TokenLimitFilter: Truncated context', [
                'initial_tokens' => $initialTokens,
                'final_tokens' => $finalTokens,
                'reduction' => $initialTokens - $finalTokens,
            ]);
        }
    }

    /**
     * Truncate individual file patches that exceed the per-file limit.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    private function truncateFiles(array $files, int $maxTokensPerFile, int $maxTokensAllFiles): array
    {
        $totalTokens = 0;

        return array_map(function (array $file) use (&$totalTokens, $maxTokensPerFile, $maxTokensAllFiles): array {
            $patch = $file['patch'];

            if ($patch === null) {
                return $file;
            }

            $patchTokens = $this->estimateTokens($patch);

            // Truncate individual large patches
            if ($patchTokens > $maxTokensPerFile) {
                $file['patch'] = $this->truncateText($patch, $maxTokensPerFile, "\n... [truncated - file too large]");
                $patchTokens = $maxTokensPerFile;
            }

            // Check if we're approaching the total file limit
            if ($totalTokens + $patchTokens > $maxTokensAllFiles) {
                // Truncate this patch to fit remaining budget
                $remainingBudget = $maxTokensAllFiles - $totalTokens;
                if ($remainingBudget > 500) {
                    $file['patch'] = $this->truncateText($patch, $remainingBudget, "\n... [truncated - token limit]");
                    $patchTokens = $remainingBudget;
                } else {
                    // No room left, remove the patch entirely
                    $file['patch'] = '[patch omitted - token limit reached]';
                    $patchTokens = 50;
                }
            }

            $totalTokens += $patchTokens;

            return $file;
        }, $files);
    }

    /**
     * Truncate linked issues to fit within budget.
     *
     * @param  array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>  $issues
     * @return array<int, array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}>
     */
    private function truncateLinkedIssues(array $issues, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($issues as $issue) {
            $issueTokens = $this->estimateIssueTokens($issue);

            if ($totalTokens + $issueTokens > $maxTokens) {
                // Truncate this issue's body and comments
                if ($totalTokens < $maxTokens - 500) {
                    $remainingBudget = $maxTokens - $totalTokens - 200; // Reserve for metadata
                    $issue = $this->truncateIssue($issue, $remainingBudget);
                    $result[] = $issue;
                }

                break;
            }

            $result[] = $issue;
            $totalTokens += $issueTokens;
        }

        return $result;
    }

    /**
     * Truncate a single issue to fit within a token budget.
     *
     * @param  array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}  $issue
     * @return array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}
     */
    private function truncateIssue(array $issue, int $maxTokens): array
    {
        // Truncate body if needed
        if ($issue['body'] !== null) {
            $bodyTokens = $this->estimateTokens($issue['body']);
            if ($bodyTokens > $maxTokens / 2) {
                $issue['body'] = $this->truncateText($issue['body'], (int) ($maxTokens / 2), '... [truncated]');
            }
        }

        // Limit comments
        $issue['comments'] = array_slice($issue['comments'], 0, 3);

        return $issue;
    }

    /**
     * Truncate PR comments to fit within budget.
     *
     * @param  array<int, array{author: string, body: string, created_at: string}>  $comments
     * @return array<int, array{author: string, body: string, created_at: string}>
     */
    private function truncatePrComments(array $comments, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($comments as $comment) {
            $commentTokens = $this->estimateTokens($comment['body']);

            if ($totalTokens + $commentTokens > $maxTokens) {
                break;
            }

            $result[] = $comment;
            $totalTokens += $commentTokens;
        }

        return $result;
    }

    /**
     * Progressive truncation of lower priority content.
     */
    private function progressiveTruncation(ContextBag $bag, int $maxContextTokens): void
    {
        // Priority order (remove from bottom):
        // 1. Review history (lowest)
        // 2. Repository context
        // 3. Project context
        // 4. PR comments
        // 5. Semantics
        // 6. Linked issues (requirements context - PR description often covers intent)
        // 7. File contents
        // 8. Impacted files (breaking change detection - no alternative source)
        // 9. Guidelines
        // 10. Files (highest - never fully remove)

        // Clear review history
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->reviewHistory = [];
        }

        // Clear repository context
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->repositoryContext = [];
        }

        // Clear project context
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->projectContext = [];
        }

        // Reduce PR comments
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->prComments = array_slice($bag->prComments, 0, 5);
        }

        // Clear PR comments entirely
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->prComments = [];
        }

        // Reduce semantics (keep first 5 files)
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->semantics = array_slice($bag->semantics, 0, 5, preserve_keys: true);
        }

        // Clear semantics entirely
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->semantics = [];
        }

        // Reduce linked issues (PR description often covers intent)
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->linkedIssues = array_slice($bag->linkedIssues, 0, 2);
        }

        // Clear linked issues entirely
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->linkedIssues = [];
        }

        // Reduce file contents (keep first 3 files)
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->fileContents = array_slice($bag->fileContents, 0, 3, preserve_keys: true);
        }

        // Clear file contents entirely
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->fileContents = [];
        }

        // Reduce impacted files (keep top 5 - critical for breaking change detection)
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->impactedFiles = array_slice($bag->impactedFiles, 0, 5);
        }

        // Clear impacted files entirely
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->impactedFiles = [];
        }

        // Reduce guidelines
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->guidelines = array_slice($bag->guidelines, 0, 1);
        }

        // Final resort: truncate more file patches
        if ($bag->estimateTokens($this->tokenCounter, $this->tokenCounterContext) > $maxContextTokens) {
            $bag->files = $this->aggressiveTruncateFiles($bag->files);
        }
    }

    /**
     * Aggressively truncate file patches when severely over limit.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    private function aggressiveTruncateFiles(array $files): array
    {
        // Keep first N files with patches, truncate the rest
        $filesWithPatches = 0;
        $maxFilesWithPatches = 15;

        return array_map(function (array $file) use (&$filesWithPatches, $maxFilesWithPatches): array {
            if ($file['patch'] !== null && $file['patch'] !== '') {
                $filesWithPatches++;

                if ($filesWithPatches > $maxFilesWithPatches) {
                    $file['patch'] = '[patch omitted - too many files]';
                } elseif (mb_strlen($file['patch']) > 2000) {
                    $file['patch'] = mb_substr($file['patch'], 0, 2000)."\n... [aggressively truncated]";
                }
            }

            return $file;
        }, $files);
    }

    /**
     * Resolve the max context tokens from metadata or fallback default.
     */
    private function resolveMaxContextTokens(ContextBag $bag): int
    {
        $budget = $bag->metadata['context_token_budget'] ?? null;

        if (is_int($budget) && $budget > 0) {
            return max($budget, self::MIN_CONTEXT_TOKENS);
        }

        if (is_string($budget) && is_numeric($budget)) {
            return max((int) $budget, self::MIN_CONTEXT_TOKENS);
        }

        return self::DEFAULT_MAX_CONTEXT_TOKENS;
    }

    /**
     * Compute per-section budgets from the max context budget.
     *
     * Budgets scale proportionally with the context window, allowing larger
     * models (200K context) to use more tokens per section than smaller models.
     * Safety caps prevent extreme allocations on very large context models.
     *
     * @return array{files_total: int, files_per: int, impacted_files: int, file_contents: int, semantics: int, issues: int, comments: int, guidelines: int, repository_context: int, review_history: int, project_context: int}
     */
    private function resolveBudgets(int $maxContextTokens): array
    {
        $filesTotal = $this->scaleBudget($maxContextTokens, self::RATIO_FILES_TOTAL, self::MAX_FILES_TOTAL);
        $filesPer = $this->scaleBudget($maxContextTokens, self::RATIO_FILES_PER, self::MAX_FILES_PER);
        $impactedFiles = $this->scaleBudget($maxContextTokens, self::RATIO_IMPACTED_FILES, self::MAX_IMPACTED_FILES);
        $fileContents = $this->scaleBudget($maxContextTokens, self::RATIO_FILE_CONTENTS, self::MAX_FILE_CONTENTS);
        $semantics = $this->scaleBudget($maxContextTokens, self::RATIO_SEMANTICS, self::MAX_SEMANTICS);
        $issues = $this->scaleBudget($maxContextTokens, self::RATIO_ISSUES);
        $comments = $this->scaleBudget($maxContextTokens, self::RATIO_COMMENTS);
        $guidelines = $this->scaleBudget($maxContextTokens, self::RATIO_GUIDELINES);
        $repositoryContext = $this->scaleBudget($maxContextTokens, self::RATIO_REPOSITORY_CONTEXT);
        $reviewHistory = $this->scaleBudget($maxContextTokens, self::RATIO_REVIEW_HISTORY);
        $projectContext = $this->scaleBudget($maxContextTokens, self::RATIO_PROJECT_CONTEXT);

        if ($filesPer > $filesTotal) {
            $filesPer = $filesTotal;
        }

        return [
            'files_total' => $filesTotal,
            'files_per' => $filesPer,
            'impacted_files' => $impactedFiles,
            'file_contents' => $fileContents,
            'semantics' => $semantics,
            'issues' => $issues,
            'comments' => $comments,
            'guidelines' => $guidelines,
            'repository_context' => $repositoryContext,
            'review_history' => $reviewHistory,
            'project_context' => $projectContext,
        ];
    }

    /**
     * Scale a budget by ratio with minimum floor and optional maximum cap.
     */
    private function scaleBudget(int $maxContextTokens, float $ratio, ?int $maxCap = null): int
    {
        $scaled = (int) round($maxContextTokens * $ratio);
        $scaled = max($scaled, self::MIN_SECTION_TOKENS);

        if ($maxCap !== null) {
            return min($scaled, $maxCap);
        }

        return $scaled;
    }

    /**
     * Truncate guidelines to fit within a token budget.
     *
     * @param  array<int, array{path: string, description: string|null, content: string}>  $guidelines
     * @return array<int, array{path: string, description: string|null, content: string}>
     */
    private function truncateGuidelines(array $guidelines, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($guidelines as $guideline) {
            $contentTokens = $this->estimateTokens($guideline['content']);
            $descriptionTokens = $guideline['description'] !== null
                ? $this->estimateTokens($guideline['description'])
                : 0;
            $guidelineTokens = $contentTokens + $descriptionTokens;

            if ($totalTokens + $guidelineTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > self::MIN_SECTION_TOKENS) {
                    $guideline['content'] = $this->truncateText(
                        $guideline['content'],
                        $remaining,
                        '... [truncated - guideline too long]'
                    );
                    $result[] = $guideline;
                }

                break;
            }

            $result[] = $guideline;
            $totalTokens += $guidelineTokens;
        }

        return $result;
    }

    /**
     * Truncate repository context to fit within a token budget.
     *
     * @param  array{readme?: string|null, contributing?: string|null}  $context
     * @return array{readme?: string|null, contributing?: string|null}
     */
    private function truncateRepositoryContext(array $context, int $maxTokens): array
    {
        if ($context === []) {
            return $context;
        }

        $totalTokens = 0;

        foreach (['contributing', 'readme'] as $key) {
            if (! isset($context[$key])) {
                continue;
            }

            if ($context[$key] === null) {
                continue;
            }

            $content = $context[$key];
            $contentTokens = $this->estimateTokens($content);

            if ($totalTokens + $contentTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > self::MIN_SECTION_TOKENS) {
                    $context[$key] = $this->truncateText(
                        $content,
                        $remaining,
                        '... [truncated - repository context too long]'
                    );
                    $totalTokens = $maxTokens;
                } else {
                    unset($context[$key]);
                }

                continue;
            }

            $totalTokens += $contentTokens;
        }

        return $context;
    }

    /**
     * Truncate review history to fit within a token budget.
     *
     * @param  array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>  $reviews
     * @return array<int, array{run_id: int, summary: string, findings_count: int, severity_breakdown: array<string, int>, key_findings: array<int, array{severity: string, category: string, title: string, file_path: string|null, line_start: int|null, fingerprint: string}>, created_at: string}>
     */
    private function truncateReviewHistory(array $reviews, int $maxTokens): array
    {
        $totalTokens = 0;
        $result = [];

        foreach ($reviews as $review) {
            $summaryTokens = $this->estimateTokens($review['summary']);
            $findingsTokens = $this->estimateTokens(json_encode($review['key_findings']) ?: '');
            $reviewTokens = $summaryTokens + $findingsTokens;

            if ($totalTokens + $reviewTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > self::MIN_SECTION_TOKENS) {
                    $review['summary'] = $this->truncateText(
                        $review['summary'],
                        $remaining,
                        '... [truncated - review history too long]'
                    );
                    $review['key_findings'] = array_slice($review['key_findings'], 0, 5);
                    $result[] = $review;
                }

                break;
            }

            $result[] = $review;
            $totalTokens += $reviewTokens;
        }

        return $result;
    }

    /**
     * Truncate project context to fit within a token budget.
     *
     * @param  array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}  $context
     * @return array{languages?: array<string>, runtime?: array{name: string, version: string}|null, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function truncateProjectContext(array $context, int $maxTokens): array
    {
        if ($context === []) {
            return $context;
        }

        $currentTokens = $this->estimateTokens(json_encode($context) ?: '');
        if ($currentTokens <= $maxTokens) {
            return $context;
        }

        if (isset($context['dependencies'])) {
            $context['dependencies'] = array_slice($context['dependencies'], 0, 10);
        }

        $currentTokens = $this->estimateTokens(json_encode($context) ?: '');
        if ($currentTokens <= $maxTokens) {
            return $context;
        }

        if (isset($context['frameworks'])) {
            $context['frameworks'] = array_slice($context['frameworks'], 0, 3);
        }

        $currentTokens = $this->estimateTokens(json_encode($context) ?: '');
        if ($currentTokens <= $maxTokens) {
            return $context;
        }

        if (isset($context['languages'])) {
            $context['languages'] = array_slice($context['languages'], 0, 3);
        }

        return $context;
    }

    /**
     * Truncate impacted files to fit within a token budget.
     *
     * Prioritizes files with higher match counts and scores.
     *
     * @param  array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>  $impactedFiles
     * @return array<int, array{file_path: string, content: string, matched_symbol: string, match_type: string, score: float, match_count: int, reason: string}>
     */
    private function truncateImpactedFiles(array $impactedFiles, int $maxTokens): array
    {
        if ($impactedFiles === []) {
            return $impactedFiles;
        }

        $totalTokens = 0;
        $result = [];
        $maxTokensPerFile = (int) ($maxTokens * self::RATIO_IMPACTED_FILE_SINGLE);

        foreach ($impactedFiles as $file) {
            $contentTokens = $this->estimateTokens($file['content']);
            $metadataTokens = 50;
            $fileTokens = $contentTokens + $metadataTokens;

            if ($fileTokens > $maxTokensPerFile) {
                $file['content'] = $this->truncateText(
                    $file['content'],
                    $maxTokensPerFile - $metadataTokens,
                    "\n... [truncated - impacted file too large]"
                );
                $fileTokens = $maxTokensPerFile;
            }

            if ($totalTokens + $fileTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > self::MIN_SECTION_TOKENS) {
                    $file['content'] = $this->truncateText(
                        $file['content'],
                        $remaining - $metadataTokens,
                        "\n... [truncated - token limit]"
                    );
                    $result[] = $file;
                }

                break;
            }

            $result[] = $file;
            $totalTokens += $fileTokens;
        }

        return $result;
    }

    /**
     * Truncate full file contents to fit within a token budget.
     *
     * @param  array<string, string>  $fileContents  path => content
     * @return array<string, string>
     */
    private function truncateFileContents(array $fileContents, int $maxTokens): array
    {
        if ($fileContents === []) {
            return $fileContents;
        }

        $totalTokens = 0;
        $result = [];
        $maxTokensPerFile = (int) ($maxTokens * self::RATIO_FILE_CONTENTS_SINGLE);

        foreach ($fileContents as $path => $content) {
            $contentTokens = $this->estimateTokens($content);

            if ($contentTokens > $maxTokensPerFile) {
                $content = $this->truncateText(
                    $content,
                    $maxTokensPerFile,
                    "\n... [truncated - file too large]"
                );
                $contentTokens = $maxTokensPerFile;
            }

            if ($totalTokens + $contentTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;
                if ($remaining > self::MIN_SECTION_TOKENS) {
                    $result[$path] = $this->truncateText(
                        $content,
                        $remaining,
                        "\n... [truncated - token limit]"
                    );
                }

                break;
            }

            $result[$path] = $content;
            $totalTokens += $contentTokens;
        }

        return $result;
    }

    /**
     * Truncate semantic analysis data to fit within a token budget.
     *
     * @param  array<string, array<string, mixed>>  $semantics  path => analysis
     * @return array<string, array<string, mixed>>
     */
    private function truncateSemantics(array $semantics, int $maxTokens): array
    {
        if ($semantics === []) {
            return $semantics;
        }

        $totalTokens = 0;
        $result = [];

        foreach ($semantics as $path => $data) {
            $dataTokens = $this->estimateTokens(json_encode($data) ?: '');

            if ($totalTokens + $dataTokens > $maxTokens) {
                $remaining = $maxTokens - $totalTokens;

                if ($remaining > self::MIN_SECTION_TOKENS) {
                    $truncatedData = $this->truncateSemanticData($data, $remaining);
                    if ($truncatedData !== []) {
                        $result[$path] = $truncatedData;
                    }
                }

                break;
            }

            $result[$path] = $data;
            $totalTokens += $dataTokens;
        }

        return $result;
    }

    /**
     * Truncate individual semantic data to fit within a token budget.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function truncateSemanticData(array $data, int $maxTokens): array
    {
        // Keep essential fields, trim arrays
        $result = [];

        if (isset($data['language'])) {
            $result['language'] = $data['language'];
        }

        // Keep first N functions
        if (isset($data['functions']) && is_array($data['functions'])) {
            $result['functions'] = array_slice($data['functions'], 0, 5);
        }

        // Keep first N classes with limited methods
        if (isset($data['classes']) && is_array($data['classes'])) {
            $classes = array_slice($data['classes'], 0, 3);
            foreach ($classes as &$class) {
                if (isset($class['methods']) && is_array($class['methods'])) {
                    $class['methods'] = array_slice($class['methods'], 0, 5);
                }
            }

            $result['classes'] = $classes;
        }

        // Keep first N imports
        if (isset($data['imports']) && is_array($data['imports'])) {
            $result['imports'] = array_slice($data['imports'], 0, 5);
        }

        // Skip calls and errors when truncating (least important)

        $currentTokens = $this->estimateTokens(json_encode($result) ?: '');
        if ($currentTokens > $maxTokens) {
            // Still too large, keep only language and minimal structure
            return [
                'language' => $data['language'] ?? 'unknown',
                'functions' => array_slice($data['functions'] ?? [], 0, 2),
                'classes' => array_slice($data['classes'] ?? [], 0, 1),
            ];
        }

        return $result;
    }

    /**
     * Truncate text to fit within a token budget.
     */
    private function truncateText(string $text, int $maxTokens, string $suffix): string
    {
        $suffixTokens = $this->estimateTokens($suffix);
        $budget = max($maxTokens - $suffixTokens, 0);

        if ($this->estimateTokens($text) <= $maxTokens) {
            return $text;
        }

        $trimmed = $this->trimToTokenBudget($text, $budget);

        return $trimmed.$suffix;
    }

    /**
     * Trim text to fit within a token budget without adding a suffix.
     */
    private function trimToTokenBudget(string $text, int $maxTokens): string
    {
        if ($maxTokens <= 0) {
            return '';
        }

        $length = mb_strlen($text);
        $low = 0;
        $high = $length;

        while ($low < $high) {
            $mid = intdiv($low + $high + 1, 2);
            $candidate = mb_substr($text, 0, $mid);

            if ($this->estimateTokens($candidate) <= $maxTokens) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return mb_substr($text, 0, $low);
    }

    /**
     * Estimate tokens for a string.
     */
    private function estimateTokens(string $text): int
    {
        return $this->tokenCounter->countTextTokens($text, $this->tokenCounterContext);
    }

    /**
     * Estimate tokens for an issue including its comments.
     *
     * @param  array{number: int, title: string, body: string|null, state: string, labels: array<int, string>, comments: array<int, array{author: string, body: string}>}  $issue
     */
    private function estimateIssueTokens(array $issue): int
    {
        $tokens = $this->estimateTokens($issue['title']);
        $tokens += $this->estimateTokens($issue['body'] ?? '');

        foreach ($issue['comments'] as $comment) {
            $tokens += $this->estimateTokens($comment['body']);
        }

        return $tokens;
    }
}
