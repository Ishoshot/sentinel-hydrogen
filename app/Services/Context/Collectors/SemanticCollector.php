<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\Policies\AdaptiveReviewLimitPolicy;
use App\Services\Semantic\Contracts\SemanticAnalyzerInterface;
use Illuminate\Support\Facades\Log;

/**
 * Collects semantic information from source code files.
 *
 * Analyzes changed files to extract functions, classes, imports,
 * and call relationships to provide structural context for AI review.
 */
final readonly class SemanticCollector implements ContextCollector
{
    /**
     * Create a new SemanticCollector instance.
     */
    public function __construct(
        private SemanticAnalyzerInterface $semanticAnalyzer,
        private AdaptiveReviewLimitPolicy $limitPolicy = new AdaptiveReviewLimitPolicy,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'semantic';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 80; // After FileContextCollector (85), before others
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        // Use file contents already collected by FileContextCollector
        if ($bag->fileContents === []) {
            Log::debug('SemanticCollector: No file contents available');

            return;
        }

        // Filter and limit files to analyze
        $limits = $this->limitPolicy->semanticLimits($repository, $bag->files);
        $filesToAnalyze = $this->selectFilesToAnalyze($bag->fileContents, $limits['max_files'], $limits['max_file_size']);

        if ($filesToAnalyze === []) {
            Log::debug('SemanticCollector: No suitable files to analyze');

            return;
        }

        // Analyze files
        $semantics = $this->semanticAnalyzer->analyzeFiles($filesToAnalyze);

        $bag->semantics = $semantics;

        Log::info('SemanticCollector: Analyzed files', [
            'files_analyzed' => count($semantics),
            'files_requested' => count($filesToAnalyze),
            'limit_max_files' => $limits['max_files'],
            'limit_max_file_size' => $limits['max_file_size'],
            'limit_tier' => $limits['tier'],
            'limit_pr_size_bucket' => $limits['pr_size_bucket'],
            'limit_source' => $limits['source'],
            'adaptive_limits_enabled' => $limits['adaptive'],
        ]);
    }

    /**
     * Select which files to analyze based on size and relevance.
     *
     * @param  array<string, string>  $fileContents
     * @return array<string, string>
     */
    private function selectFilesToAnalyze(array $fileContents, int $maxFiles, int $maxFileSize): array
    {
        $candidates = [];

        foreach ($fileContents as $filename => $content) {
            // Skip files that are too large
            if (mb_strlen($content) > $maxFileSize) {
                continue;
            }

            $candidates[$filename] = $content;
        }

        // Limit number of files
        return array_slice($candidates, 0, max(1, $maxFiles), true);
    }
}
