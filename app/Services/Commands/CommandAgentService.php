<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Enums\AiProvider;
use App\Enums\CommandType;
use App\Exceptions\NoProviderKeyException;
use App\Models\AiOption;
use App\Models\CodeIndex;
use App\Models\CommandRun;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;
use RuntimeException;
use Throwable;

/**
 * AI agent service for executing commands with tool calling capabilities.
 *
 * Implements an agentic loop where the LLM can call tools to gather
 * information and synthesize a response.
 */
final readonly class CommandAgentService implements CommandAgentServiceContract
{
    private const int MAX_ITERATIONS = 10;

    private const int THINKING_BUDGET = 8192;

    /**
     * Create a new CommandAgentService instance.
     */
    public function __construct(
        private CodeSearchServiceContract $searchService,
        private ProviderKeyResolver $keyResolver,
        private PullRequestContextServiceContract $prContextService,
    ) {}

    /**
     * Execute a command using the AI agent.
     *
     * @return array{answer: string, tool_calls: array<int, array{name: string, arguments: array<string, mixed>, result: string}>, iterations: int, metrics: array{input_tokens: int, output_tokens: int, thinking_tokens: int, cache_creation_input_tokens: int, cache_read_input_tokens: int, duration_ms: int, model: string, provider: string}, pr_metadata: array{pr_title?: string, pr_additions?: int, pr_deletions?: int, pr_changed_files?: int, pr_context_included?: bool}|null}
     */
    public function execute(CommandRun $commandRun): array
    {
        $startTime = microtime(true);

        $repository = $commandRun->repository;
        if ($repository === null) {
            throw new RuntimeException('CommandRun has no associated repository');
        }

        // Resolve provider key (BYOK)
        $providerKey = $this->resolveProviderKey($repository);
        $aiProvider = $providerKey->provider;
        $provider = $this->mapToProvider($aiProvider);
        $model = $this->resolveModel($aiProvider, $providerKey);
        $apiKey = $providerKey->encrypted_key;

        // Build tools
        $tools = $this->buildTools($commandRun);

        // Build system prompt
        $systemPrompt = $this->buildSystemPrompt($commandRun);

        // Build initial user message
        $userMessage = $this->buildUserMessage($commandRun);

        // Add PR context if this is a pull request command
        $prContext = $this->prContextService->buildContext($commandRun);
        $prMetadata = $this->prContextService->getMetadata($commandRun);

        if ($prContext !== null) {
            $userMessage = $prContext."\n".$userMessage;
        }

        // Determine if extended thinking should be enabled
        $enableThinking = $aiProvider === AiProvider::Anthropic
            && config('prism.providers.anthropic.default_thinking_budget', 2048) > 0;

        $providerOptions = $this->buildProviderOptions($aiProvider, $enableThinking);

        $temperature = $enableThinking ? 1 : 0.3;

        Log::debug('Starting command agent execution', [
            'command_run_id' => $commandRun->id,
            'command_type' => $commandRun->command_type->value,
            'provider' => $provider->value,
            'model' => $model,
            'thinking_enabled' => $enableThinking,
        ]);

        /** @var array<int, array{name: string, arguments: array<string, mixed>, result: string}> $allToolCalls */
        $allToolCalls = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalThinkingTokens = 0;
        $cacheCreationTokens = 0;
        $cacheReadTokens = 0;

        // Execute with max steps (Prism handles the agentic loop internally)
        try {
            $response = Prism::text()
                ->using($provider, $model, ['api_key' => $apiKey])
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($userMessage)
                ->withTools($tools)
                ->withToolChoice(ToolChoice::Auto)
                ->withMaxSteps(self::MAX_ITERATIONS)
                ->withMaxTokens(4096)
                ->usingTemperature($temperature)
                ->withProviderOptions($providerOptions)
                ->withClientOptions(['timeout' => 300])
                ->asText();

            // Collect tool calls from all steps
            foreach ($response->steps as $step) {
                foreach ($step->toolCalls as $toolCall) {
                    $allToolCalls[] = [
                        'name' => $toolCall->name,
                        'arguments' => $toolCall->arguments(),
                        'result' => '', // Will be populated below
                    ];
                }
            }

            // Match tool calls with results
            foreach ($response->toolResults as $index => $toolResult) {
                if (isset($allToolCalls[$index])) {
                    $result = $toolResult->result;
                    $allToolCalls[$index]['result'] = is_array($result)
                        ? json_encode($result, JSON_THROW_ON_ERROR)
                        : (string) ($result ?? '');
                }
            }

            $totalInputTokens = $response->usage->promptTokens;
            $totalOutputTokens = $response->usage->completionTokens;

            // Extract thinking tokens if available (check Meta object)
            if (property_exists($response->meta, 'thinkingTokens') && $response->meta->thinkingTokens !== null) {
                $totalThinkingTokens = (int) $response->meta->thinkingTokens;
            }

            // Extract cache metrics if available (Anthropic prompt caching)
            if (property_exists($response->usage, 'cacheCreationInputTokens') && $response->usage->cacheCreationInputTokens !== null) {
                $cacheCreationTokens = (int) $response->usage->cacheCreationInputTokens;
            }

            if (isset($response->usage->cacheReadInputTokens)) {
                $cacheReadTokens = (int) $response->usage->cacheReadInputTokens;
            }

            $answer = $response->text;
            $iterations = count($response->steps);

        } catch (Throwable $throwable) {
            Log::error('Command agent execution failed', [
                'command_run_id' => $commandRun->id,
                'error' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        Log::info('Command agent execution completed', [
            'command_run_id' => $commandRun->id,
            'iterations' => $iterations,
            'tool_calls' => count($allToolCalls),
            'duration_ms' => $durationMs,
        ]);

        return [
            'answer' => $answer,
            'tool_calls' => array_values($allToolCalls),
            'iterations' => $iterations,
            'metrics' => [
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
                'thinking_tokens' => $totalThinkingTokens,
                'cache_creation_input_tokens' => $cacheCreationTokens,
                'cache_read_input_tokens' => $cacheReadTokens,
                'duration_ms' => $durationMs,
                'model' => $model,
                'provider' => $provider->value,
            ],
            'pr_metadata' => $prMetadata,
        ];
    }

    /**
     * Build the tools available to the agent.
     *
     * @return array<int, PrismTool>
     */
    private function buildTools(CommandRun $commandRun): array
    {
        return [
            $this->buildSearchCodeTool($commandRun),
            $this->buildSearchPatternTool($commandRun),
            $this->buildFindSymbolTool($commandRun),
            $this->buildListFilesTool($commandRun),
            $this->buildReadFileTool($commandRun),
            $this->buildGetFileStructureTool($commandRun),
        ];
    }

    /**
     * Build the search_code tool for hybrid code search.
     */
    private function buildSearchCodeTool(CommandRun $commandRun): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('search_code')
            ->for('Search the codebase using hybrid keyword and semantic search. Use this to find relevant code files, classes, methods, or functions.')
            ->withStringParameter('query', 'The search query - can be natural language describing what you are looking for, or specific code patterns/names')
            ->withNumberParameter('limit', 'Maximum number of results to return (default: 10, max: 20)')
            ->using(function (string $query, ?int $limit = null) use ($repository): string {
                if ($repository === null) {
                    return 'Error: Repository not available for search.';
                }

                $limit = min($limit ?? 10, 20);

                $results = $this->searchService->search($repository, $query, $limit);

                if ($results === []) {
                    return 'No results found for the given query.';
                }

                $formatted = array_map(function (array $result, int $index): string {
                    $matchType = $result['match_type'];
                    $score = number_format($result['score'], 2);
                    $filePath = $result['file_path'];
                    $content = $result['content'];

                    // Truncate content for display
                    if (mb_strlen($content) > 300) {
                        $content = mb_substr($content, 0, 300).'...';
                    }

                    return sprintf(
                        "[%d] %s (score: %s, type: %s)\n%s",
                        $index + 1,
                        $filePath,
                        $score,
                        $matchType,
                        $content
                    );
                }, $results, array_keys($results));

                return 'Found '.count($results)." results:\n\n".implode("\n\n---\n\n", $formatted);
            });
    }

    /**
     * Build the search_pattern tool for grep-like exact pattern matching.
     */
    private function buildSearchPatternTool(CommandRun $commandRun): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('search_pattern')
            ->for('Search for exact text patterns or regex in the codebase. Use this for precise pattern matching like finding specific function calls, variable names, or code patterns. More precise than search_code.')
            ->withStringParameter('pattern', 'The exact text or regex pattern to search for (e.g., "function authenticate", "->validate(", "class.*Controller")')
            ->withStringParameter('file_type', 'Optional: filter by file type (e.g., "php", "js", "ts")')
            ->withNumberParameter('limit', 'Maximum number of results to return (default: 20, max: 50)')
            ->using(function (string $pattern, ?string $fileType = null, ?int $limit = null) use ($repository): string {
                if ($repository === null) {
                    return 'Error: Repository not available for search.';
                }

                $limit = min($limit ?? 20, 50);

                $query = CodeIndex::where('repository_id', $repository->id);

                if ($fileType !== null && $fileType !== '') {
                    $query->where('file_type', $fileType);
                }

                // Search for exact pattern in content
                $query->where('content', 'LIKE', '%'.$pattern.'%');

                $results = $query->select(['file_path', 'file_type', 'content'])
                    ->limit($limit)
                    ->get();

                if ($results->isEmpty()) {
                    return 'No matches found for pattern: '.$pattern;
                }

                $formatted = $results->map(function (CodeIndex $index, int $idx) use ($pattern): string {
                    $matches = [];
                    $lines = explode("\n", $index->content);

                    foreach ($lines as $lineNum => $line) {
                        if (mb_stripos($line, $pattern) !== false) {
                            $matches[] = sprintf('  %4d | %s', $lineNum + 1, mb_trim($line));
                            if (count($matches) >= 5) {
                                $matches[] = '  ... (more matches in this file)';
                                break;
                            }
                        }
                    }

                    return sprintf(
                        "[%d] %s (%s)\n%s",
                        $idx + 1,
                        $index->file_path,
                        $index->file_type,
                        implode("\n", $matches)
                    );
                })->join("\n\n");

                return 'Found matches in '.count($results)." files:\n\n".$formatted;
            });
    }

    /**
     * Build the find_symbol tool to find code by symbol name.
     */
    private function buildFindSymbolTool(CommandRun $commandRun): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('find_symbol')
            ->for('Find code definitions by symbol name (class, method, function, trait, interface). Use this to locate where something is defined.')
            ->withStringParameter('symbol_name', 'The name of the symbol to find (e.g., "UserController", "authenticate", "handleRequest")')
            ->withNumberParameter('limit', 'Maximum number of results (default: 5, max: 10)')
            ->using(function (string $symbolName, ?int $limit = null) use ($repository): string {
                if ($repository === null) {
                    return 'Error: Repository not available for search.';
                }

                $limit = min($limit ?? 5, 10);

                $results = $this->searchService->findSymbol($repository, $symbolName, $limit);

                if ($results === []) {
                    return 'No symbol found matching: '.$symbolName;
                }

                $formatted = array_map(function (array $result, int $index): string {
                    $filePath = $result['file_path'];
                    $symbol = $result['symbol_name'];
                    $chunkType = $result['chunk_type'];
                    $content = $result['content'];

                    // Truncate content
                    if (mb_strlen($content) > 400) {
                        $content = mb_substr($content, 0, 400).'...';
                    }

                    return sprintf(
                        "[%d] %s (%s) in %s\n%s",
                        $index + 1,
                        $symbol,
                        $chunkType,
                        $filePath,
                        $content
                    );
                }, $results, array_keys($results));

                return 'Found '.count($results)." symbols:\n\n".implode("\n\n---\n\n", $formatted);
            });
    }

    /**
     * Build the list_files tool to list files in the repository.
     */
    private function buildListFilesTool(CommandRun $commandRun): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('list_files')
            ->for('List files in the indexed repository. Use this to explore the codebase structure, find files by path pattern, or filter by file type.')
            ->withStringParameter('path_pattern', 'Optional: filter files by path pattern (e.g., "app/Models", "tests/", "Controller")')
            ->withStringParameter('file_type', 'Optional: filter by file type (e.g., "php", "js", "vue")')
            ->withNumberParameter('limit', 'Maximum number of files to return (default: 30, max: 100)')
            ->using(function (?string $pathPattern = null, ?string $fileType = null, ?int $limit = null) use ($repository): string {
                if ($repository === null) {
                    return 'Error: Repository not available for listing files.';
                }

                $limit = min($limit ?? 30, 100);

                $query = CodeIndex::where('repository_id', $repository->id);

                if ($pathPattern !== null && $pathPattern !== '') {
                    $query->where('file_path', 'LIKE', '%'.$pathPattern.'%');
                }

                if ($fileType !== null && $fileType !== '') {
                    $query->where('file_type', $fileType);
                }

                $files = $query->select(['file_path', 'file_type'])
                    ->orderBy('file_path')
                    ->limit($limit)
                    ->get();

                if ($files->isEmpty()) {
                    $filters = [];
                    if ($pathPattern) {
                        $filters[] = 'path pattern: '.$pathPattern;
                    }

                    if ($fileType) {
                        $filters[] = 'file type: '.$fileType;
                    }

                    return 'No files found'.($filters !== [] ? ' matching '.implode(', ', $filters) : '').'.';
                }

                // Group by directory for better readability
                $grouped = $files->groupBy(fn (CodeIndex $file): string => dirname($file->file_path));

                $output = [];
                foreach ($grouped as $directory => $directoryFiles) {
                    $output[] = $directory.'/';
                    foreach ($directoryFiles as $file) {
                        $output[] = '  - '.basename((string) $file->file_path).sprintf(' (%s)', $file->file_type);
                    }
                }

                $totalCount = CodeIndex::where('repository_id', $repository->id)
                    ->when($pathPattern, fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('file_path', 'LIKE', '%'.$pathPattern.'%'))
                    ->when($fileType, fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('file_type', $fileType))
                    ->count();

                $header = sprintf('Showing %s of %s files', $files->count(), $totalCount);
                if ($totalCount > $limit) {
                    $header .= ' (use path_pattern or file_type to filter)';
                }

                return $header.":\n\n".implode("\n", $output);
            });
    }

    /**
     * Build the read_file tool to read indexed file content.
     */
    private function buildReadFileTool(CommandRun $commandRun): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('read_file')
            ->for('Read the full content of a file from the indexed codebase. Use this after search_code to get complete file contents.')
            ->withStringParameter('file_path', 'The path to the file to read (as returned by search_code)')
            ->using(function (string $filePath) use ($repository): string {
                if ($repository === null) {
                    return 'Error: Repository not available for reading files.';
                }

                $codeIndex = CodeIndex::where('repository_id', $repository->id)
                    ->where('file_path', $filePath)
                    ->first();

                if ($codeIndex === null) {
                    return sprintf('File not found in index: %s. The file may not be indexed or the path may be incorrect.', $filePath);
                }

                $content = $codeIndex->content;
                $lines = mb_substr_count($content, "\n") + 1;
                $fileType = $codeIndex->file_type;

                // Add line numbers for easier reference
                $numberedLines = [];
                foreach (explode("\n", $content) as $lineNum => $line) {
                    $numberedLines[] = sprintf('%4d | %s', $lineNum + 1, $line);
                }

                return sprintf(
                    "File: %s\nType: %s\nLines: %d\n\n%s",
                    $filePath,
                    $fileType,
                    $lines,
                    implode("\n", $numberedLines)
                );
            });
    }

    /**
     * Build the get_file_structure tool to get AST structure of a file.
     */
    private function buildGetFileStructureTool(CommandRun $commandRun): PrismTool
    {
        $repository = $commandRun->repository;

        return Tool::as('get_file_structure')
            ->for('Get the structural analysis (classes, methods, functions) of a file. Use this to understand the organization of a file without reading all content.')
            ->withStringParameter('file_path', 'The path to the file to analyze')
            ->using(function (string $filePath) use ($repository): string {
                if ($repository === null) {
                    return 'Error: Repository not available for file structure analysis.';
                }

                $codeIndex = CodeIndex::where('repository_id', $repository->id)
                    ->where('file_path', $filePath)
                    ->first();

                if ($codeIndex === null) {
                    return sprintf('File not found in index: %s. The file may not be indexed or the path may be incorrect.', $filePath);
                }

                $structure = $codeIndex->structure;

                if ($structure === null || $structure === []) {
                    return sprintf('No structural analysis available for: %s. This file type may not support structural analysis.', $filePath);
                }

                return sprintf(
                    "File: %s\nType: %s\n\nStructure:\n%s",
                    $filePath,
                    $codeIndex->file_type,
                    json_encode($structure, JSON_PRETTY_PRINT)
                );
            });
    }

    /**
     * Build the system prompt based on command type.
     */
    private function buildSystemPrompt(CommandRun $commandRun): string
    {
        return <<<'PROMPT'
You are Sentinel, an AI code assistant integrated with GitHub. You help developers understand and analyze their codebase.

You have access to powerful tools to search and analyze the indexed repository. Use these tools to gather information before providing your answer.

Available Tools:
- search_code: Hybrid semantic + keyword search for finding relevant code (best for natural language queries)
- search_pattern: Grep-like exact pattern matching (best for finding specific code patterns, function calls, variable names)
- find_symbol: Find definitions by symbol name (class, method, function)
- list_files: Browse the codebase structure and list files by path or type
- read_file: Read complete file contents with line numbers
- get_file_structure: Get AST structure analysis of a file

Guidelines:
- Start with search_code or list_files to explore, then use search_pattern for precise matches
- Use find_symbol to locate where classes/methods/functions are defined
- Use read_file to get full context after finding relevant files
- Always reference specific files and line numbers in your answer
- Be concise but thorough
- If you cannot find what you are looking for, explain what you searched for and suggest alternatives

PROMPT.match ($commandRun->command_type) {
            CommandType::Explain => <<<'PROMPT'

Your task is to EXPLAIN code, concepts, or database columns. Provide clear, educational explanations that help developers understand how things work. Include:
- What the code/concept does
- How it works
- Why it is designed this way
- Any important relationships or dependencies
PROMPT,
            CommandType::Analyze => <<<'PROMPT'

Your task is to perform DEEP ANALYSIS of code sections. Provide thorough analysis including:
- Code quality assessment
- Potential issues or edge cases
- Performance considerations
- Architectural patterns used
- Suggestions for improvement
PROMPT,
            CommandType::Review => <<<'PROMPT'

Your task is to REVIEW specific files or changes. Provide constructive code review feedback including:
- Code quality issues
- Potential bugs or security concerns
- Best practice violations
- Suggestions for improvement
- Positive aspects worth highlighting
PROMPT,
            CommandType::Summarize => <<<'PROMPT'

Your task is to SUMMARIZE the PR or changes. Provide a clear summary including:
- What changed and why
- Key files modified
- Impact of the changes
- Any notable patterns or concerns
PROMPT,
            CommandType::Find => <<<'PROMPT'

Your task is to FIND usages and references. Search thoroughly and report:
- All locations where the target is used
- The context of each usage
- Any patterns in how it is used
- Dependencies and dependents
PROMPT,
        };
    }

    /**
     * Build the user message from the command run.
     */
    private function buildUserMessage(CommandRun $commandRun): string
    {
        $query = $commandRun->query;
        $commandType = $commandRun->command_type->description();

        // Note: PR context is added separately by PullRequestContextService
        return "Command: {$commandType}\n\nQuery: {$query}";
    }

    /**
     * Resolve the provider key for the repository.
     */
    private function resolveProviderKey(Repository $repository): ProviderKey
    {
        $providers = [AiProvider::Anthropic, AiProvider::OpenAI];

        foreach ($providers as $aiProvider) {
            $providerKey = $this->keyResolver->getProviderKey($repository, $aiProvider);

            if ($providerKey instanceof ProviderKey) {
                return $providerKey;
            }
        }

        throw NoProviderKeyException::noProvidersConfigured();
    }

    /**
     * Map AiProvider enum to Prism Provider enum.
     */
    private function mapToProvider(AiProvider $aiProvider): Provider
    {
        return match ($aiProvider) {
            AiProvider::Anthropic => Provider::Anthropic,
            AiProvider::OpenAI => Provider::OpenAI,
        };
    }

    /**
     * Build provider-specific options for the Prism request.
     *
     * @return array<string, mixed>
     */
    private function buildProviderOptions(AiProvider $aiProvider, bool $enableThinking): array
    {
        if ($aiProvider !== AiProvider::Anthropic) {
            return [];
        }

        $options = ['cacheType' => 'ephemeral'];

        if ($enableThinking) {
            $options['thinking'] = ['enabled' => true, 'budget_tokens' => self::THINKING_BUDGET];
        }

        return $options;
    }

    /**
     * Resolve the AI model to use.
     */
    private function resolveModel(AiProvider $aiProvider, ProviderKey $providerKey): string
    {
        // 1. Use model from provider key if user selected one
        if ($providerKey->providerModel !== null) {
            return $providerKey->providerModel->identifier;
        }

        // 2. Get default from database
        $defaultModel = AiOption::getDefault($aiProvider);
        if ($defaultModel instanceof AiOption) {
            return $defaultModel->identifier;
        }

        // 3. Hardcoded fallback (safety net)
        return match ($aiProvider) {
            AiProvider::Anthropic => 'claude-sonnet-4-5-20250929',
            AiProvider::OpenAI => 'gpt-4o',
        };
    }
}
