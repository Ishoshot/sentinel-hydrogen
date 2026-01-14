<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\DataTransferObjects\SentinelConfig\GuidelineConfig;
use App\DataTransferObjects\SentinelConfig\SentinelConfig;
use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects custom guideline files defined in .sentinel/config.yaml.
 *
 * Fetches team-specific documentation files that help the AI understand
 * project conventions, coding standards, and custom review guidelines.
 */
final readonly class GuidelinesCollector implements ContextCollector
{
    /**
     * Maximum number of guideline files to fetch.
     */
    private const int MAX_GUIDELINES = 5;

    /**
     * Maximum content length per file (in bytes).
     * 50KB limit per file.
     */
    private const int MAX_FILE_SIZE = 51200;

    /**
     * Allowed file extensions for guidelines.
     *
     * @var array<string>
     */
    private const array ALLOWED_EXTENSIONS = ['md', 'mdx', 'blade.php'];

    /**
     * Create a new GuidelinesCollector instance.
     */
    public function __construct(private GitHubApiServiceContract $gitHubApiService) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'guidelines';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 45; // After RepositoryContextCollector (50), before filters
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

        // Get guidelines configuration from metadata
        $guidelineConfigs = $this->getGuidelinesConfig($bag);

        if ($guidelineConfigs === []) {
            return;
        }

        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            Log::warning('GuidelinesCollector: Repository has no installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            Log::warning('GuidelinesCollector: Invalid repository full name', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);
        $installationId = $installation->installation_id;

        $guidelines = [];
        $fetchedCount = 0;

        foreach ($guidelineConfigs as $config) {
            if ($fetchedCount >= self::MAX_GUIDELINES) {
                Log::info('GuidelinesCollector: Maximum guidelines limit reached', [
                    'limit' => self::MAX_GUIDELINES,
                    'total_configured' => count($guidelineConfigs),
                ]);

                break;
            }

            if (! $this->isAllowedFileType($config->path)) {
                Log::debug('GuidelinesCollector: Skipping unsupported file type', [
                    'path' => $config->path,
                    'allowed_extensions' => self::ALLOWED_EXTENSIONS,
                ]);

                continue;
            }

            $content = $this->fetchGuideline($installationId, $owner, $repo, $config->path);

            if ($content !== null) {
                $guidelines[] = [
                    'path' => $config->path,
                    'description' => $config->description,
                    'content' => $content,
                ];
                $fetchedCount++;
            }
        }

        $bag->guidelines = $guidelines;

        Log::info('GuidelinesCollector: Collected guidelines', [
            'repository' => $fullName,
            'configured' => count($guidelineConfigs),
            'fetched' => count($guidelines),
        ]);
    }

    /**
     * Get guidelines configuration from the context bag metadata.
     *
     * @return array<int, GuidelineConfig>
     */
    private function getGuidelinesConfig(ContextBag $bag): array
    {
        $sentinelConfigData = $bag->metadata['sentinel_config'] ?? null;

        if (! is_array($sentinelConfigData)) {
            return [];
        }

        /** @var array<string, mixed> $sentinelConfigData */
        $sentinelConfig = SentinelConfig::fromArray($sentinelConfigData);

        return $sentinelConfig->guidelines;
    }

    /**
     * Check if a file path has an allowed extension.
     */
    private function isAllowedFileType(string $path): bool
    {
        $lowerPath = mb_strtolower($path);

        return array_any(self::ALLOWED_EXTENSIONS, fn ($extension): bool => str_ends_with($lowerPath, '.'.$extension));
    }

    /**
     * Fetch a guideline file from GitHub.
     */
    private function fetchGuideline(
        int $installationId,
        string $owner,
        string $repo,
        string $path
    ): ?string {
        try {
            $response = $this->gitHubApiService->getFileContents(
                $installationId,
                $owner,
                $repo,
                $path
            );

            $content = $this->extractContent($response, $path);

            if ($content === null) {
                return null;
            }

            // Enforce size limit
            if (mb_strlen($content, '8bit') > self::MAX_FILE_SIZE) {
                Log::info('GuidelinesCollector: Truncating oversized guideline', [
                    'path' => $path,
                    'original_size' => mb_strlen($content, '8bit'),
                    'max_size' => self::MAX_FILE_SIZE,
                ]);

                return $this->truncateContent($content, $path);
            }

            return $content;
        } catch (Throwable $throwable) {
            Log::debug('GuidelinesCollector: Failed to fetch guideline', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract content from GitHub API response.
     */
    private function extractContent(mixed $response, string $path): ?string
    {
        // Handle string response (already decoded)
        if (is_string($response)) {
            return $response;
        }

        // Handle array response (base64 encoded)
        if (is_array($response) && isset($response['content']) && is_string($response['content'])) {
            $content = $response['content'];
            $encoding = $response['encoding'] ?? 'base64';

            if ($encoding === 'base64') {
                $decoded = base64_decode(str_replace("\n", '', $content), true);

                return $decoded !== false ? $decoded : null;
            }

            return $content;
        }

        Log::debug('GuidelinesCollector: Unexpected response format', [
            'path' => $path,
        ]);

        return null;
    }

    /**
     * Truncate content if it exceeds the maximum size.
     */
    private function truncateContent(string $content, string $path): string
    {
        // Convert to character limit (rough approximation)
        $charLimit = (int) (self::MAX_FILE_SIZE * 0.9);
        $truncated = mb_substr($content, 0, $charLimit);

        // Try to break at a paragraph or line boundary
        $lastParagraph = mb_strrpos($truncated, "\n\n");
        $lastLine = mb_strrpos($truncated, "\n");

        if ($lastParagraph !== false && $lastParagraph > $charLimit * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastParagraph);
        } elseif ($lastLine !== false && $lastLine > $charLimit * 0.9) {
            $truncated = mb_substr($truncated, 0, $lastLine);
        }

        $filename = basename($path);

        return $truncated."\n\n[{$filename} truncated due to size limit]";
    }
}
