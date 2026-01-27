# Interactive GitHub Command Capabilities - Implementation Plan

**Version**: 1.0
**Status**: Ready for Implementation
**Timeline**: 6 weeks (4 weeks foundation + 2 weeks critical upgrades)
**Last Updated**: 2026-01-19

---

## Executive Summary

Add interactive GitHub command capabilities to Sentinel, enabling users to @-mention Sentinel in issues and PR comments for **Claude-quality code analysis** with competitive advantages:

**Example**: "@sentinel explain the is_active column on User model"

### What Makes This Competitive

**Phase A (4 weeks)**: Foundation

-   Full codebase indexing with semantic search
-   Agent-based exploration with tool access
-   Hybrid search (keyword + semantic embeddings)
-   Aggressive caching for performance

**Phase A+ (2 weeks)**: Critical Competitive Advantages

-   ✅ **Extended Thinking** - Claude's 8192-token reasoning for deeper analysis
-   ✅ **Prompt Caching** - 90% cost reduction, 2-3x faster responses
-   ✅ **PR Context Integration** - Auto-include diff, description, comments
-   ✅ **Incremental Indexing** - 95% faster, 90% cheaper updates

### Market Position

**vs GitHub Copilot Chat**: Better context, shows work, cross-file understanding
**vs Cursor**: GitHub native, team context, BYOK cost control
**vs Claude in GitHub**: Faster (pre-indexed), cached, PR-optimized

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Data Models](#data-models)
3. [Core Features](#core-features)
4. [Phase A+ Enhancements](#phase-a-enhancements)
5. [Implementation Timeline](#implementation-timeline)
6. [Testing Strategy](#testing-strategy)
7. [Success Criteria](#success-criteria)
8. [Future Scope](#future-scope)

---

## Architecture Overview

### System Flow

```
GitHub Issue/PR Comment with @sentinel
  ↓
IssueComment Webhook
  ↓
Parse Command & Check Permissions
  ↓
Create CommandRun (queued)
  ↓
Execute Agent with Tools (in_progress)
  ├─ Hybrid Search (keyword + semantic)
  ├─ Read Files (from indexed cache)
  ├─ Get Structure (AST analysis)
  └─ Extended Thinking (8K tokens)
  ↓
Post Response to GitHub (completed)
```

### Push Webhook → Incremental Indexing

```
Push to Repository
  ↓
ProcessPushWebhook
  ↓
Extract Changed Files (added, modified, removed)
  ↓
IndexCodeBatchJob (only changed files)
  ↓
GenerateCodeEmbeddingsJob (per file)
  ↓
Index Updated (embeddings cached)
```

### Key Architectural Decisions

1. **PostgreSQL pgvector** for vector search (no new infrastructure)
2. **Agent with Tools** instead of single LLM call (Claude's approach)
3. **Incremental Indexing** instead of full reindex (production essential)
4. **Prompt Caching** for repeated context (90% cost reduction)
5. **Extended Thinking** enabled by default (competitive quality)

---

## Data Models

### 1. command_runs

Tracks command execution from GitHub comments.

```php
Schema::create('command_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workspace_id')->constrained()->noActionOnDelete();
    $table->foreignId('repository_id')->constrained()->noActionOnDelete();
    $table->foreignId('initiated_by_id')->nullable()->constrained('users')->nullOnDelete();

    // GitHub context
    $table->string('external_reference'); // github:comment:{id}
    $table->bigInteger('github_comment_id');
    $table->integer('issue_number')->nullable();
    $table->boolean('is_pull_request')->default(false);

    // Command details
    $table->string('command_type'); // explain, analyze, review, summarize, find
    $table->text('query');

    // Execution
    $table->string('status'); // queued, in_progress, completed, failed
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->integer('duration_seconds')->nullable();

    // Results
    $table->jsonb('response')->nullable();
    $table->jsonb('context_snapshot')->nullable();
    $table->jsonb('metrics')->nullable(); // Token usage, cache hits
    $table->jsonb('metadata')->nullable(); // Tool calls, iterations, PR context

    $table->timestamp('created_at');

    $table->index(['workspace_id', 'created_at']);
    $table->index(['repository_id', 'created_at']);
    $table->index(['status']);
    $table->index(['command_type']);
    $table->index(['github_comment_id']);
});
```

### 2. code_indexes

Stores indexed file content and structure.

```php
Schema::create('code_indexes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
    $table->string('commit_sha')->index();
    $table->string('file_path')->index();
    $table->string('file_type', 20);
    $table->text('content');
    $table->jsonb('structure')->nullable(); // From SemanticAnalyzer
    $table->jsonb('metadata')->nullable(); // Lines, size, dependencies
    $table->timestamp('indexed_at');
    $table->timestamp('created_at');

    $table->index(['repository_id', 'commit_sha']);
    $table->index(['repository_id', 'file_path']);
    $table->unique(['repository_id', 'commit_sha', 'file_path']);
});
```

### 3. code_embeddings

Vector embeddings for semantic search.

```php
Schema::create('code_embeddings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('code_index_id')->constrained()->cascadeOnDelete();
    $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
    $table->string('chunk_type', 50); // file, class, method, function
    $table->string('symbol_name')->nullable();
    $table->text('content');
    $table->jsonb('metadata')->nullable();
    $table->timestamp('created_at');

    $table->index(['repository_id', 'chunk_type']);
    $table->index(['code_index_id']);
    $table->index(['symbol_name']);
});

// Add vector column (pgvector)
DB::statement('ALTER TABLE code_embeddings ADD COLUMN embedding vector(1536)');

// Create HNSW index for fast similarity search
DB::statement('CREATE INDEX code_embeddings_embedding_idx ON code_embeddings USING hnsw (embedding vector_cosine_ops)');
```

---

## Core Features

### 1. Command Parsing

**File**: `app/Services/Commands/CommandParser.php`

Parses `@sentinel <command> <query>` from GitHub comments.

**Supported Commands**:

-   `explain` - Explain code/concept/column (default)
-   `analyze` - Deep analysis of code section
-   `review` - Review specific file/changes
-   `summarize` - Summarize PR/changes
-   `find` - Find usages/references

**Context Hints Extraction**:

-   File paths: `app/Models/User.php`
-   Symbols: `User::isActive`, `CreateWorkspace`
-   Line numbers: `line 42`

### 2. Permission Validation

**File**: `app/Services/Commands/CommandPermissionService.php`

**Checks**:

1. Workspace membership (via ProviderIdentity)
2. Repository access (installation includes repo)
3. Plan limits (commands per month)
4. BYOK requirement (AI provider keys configured)

### 3. Code Indexing (Incremental)

**File**: `app/Services/CodeIndexing/CodeIndexingService.php`

**Incremental Approach** (Phase A+):

-   Only index changed files on push (added, modified)
-   Delete embeddings for removed files
-   Update existing indexes for modified files
-   95% faster than full reindex

**Structure Analysis**:

-   Uses SemanticAnalyzerService for AST parsing
-   Extracts classes, methods, functions
-   Stores in `code_indexes.structure`

**Chunking Strategy**:

1. Full file (broad searches)
2. Class-level chunks
3. Method-level chunks
4. Function-level chunks

### 4. Hybrid Search

**File**: `app/Services/CodeIndexing/CodeSearchService.php`

**Combines**:

-   **Keyword Search** (60% weight): Fast, exact matches via database queries
-   **Semantic Search** (40% weight): Conceptual matches via pgvector similarity

**Caching**:

-   15-minute TTL on search results
-   Cache key: `code_search:{repo_id}:{query_hash}`

**Performance**:

-   <2s uncached
-   <100ms cached

### 5. Agent Execution

**File**: `app/Services/Commands/CommandAgentService.php`

**Agentic Loop**:

1. LLM calls tools to gather information
2. Tool results added to conversation
3. LLM decides next tool or final answer
4. Max 10 iterations (safety limit)

**Tools Available**:

-   `search_code` - Hybrid search (keyword + semantic)
-   `read_file` - Read from indexed cache
-   `get_file_structure` - Get AST structure

**Extended Thinking** (Phase A+):

```php
$response = Prism::text()
    ->using($providerKey->provider, $providerKey->model)
    ->withThinkingBudget(8192) // Enable extended thinking
    ->withSystemPrompt($systemPrompt)
    ->withMessages($messages)
    ->withTools($tools)
    ->asText();
```

---

## Phase A+ Enhancements

### 1. Extended Thinking (Week 5)

**What**: Enable Claude's extended thinking (8192 tokens) for deeper reasoning.

**Implementation**:

```php
// In CommandAgentService::execute()
$response = Prism::text()
    ->using($providerKey->provider, $providerKey->model)
    ->withThinkingBudget(8192) // ADD THIS
    ->withSystemPrompt($systemPrompt)
    ->withMessages($messages)
    ->withTools($tools)
    ->asText();
```

**Impact**:

-   🎯 Deeper code analysis (architectural patterns, edge cases)
-   🎯 Better recommendations (considers trade-offs)
-   🎯 More accurate answers (reasons through complexity)

**Timeline**: 2 days (just add parameter + test)

---

### 2. Prompt Caching (Week 5)

**What**: Cache expensive context (system prompt, file contents) for 5 minutes.

**Implementation**:

```php
// In CommandAgentService::execute()
$messages = [
    [
        'role' => 'system',
        'content' => $systemPrompt,
        'cache_control' => ['type' => 'ephemeral'], // Cache for 5 min
    ],
    [
        'role' => 'user',
        'content' => $userMessage,
    ],
];

// When adding tool results (large context)
$messages[] = [
    'role' => 'tool',
    'tool_call_id' => $toolCall['id'],
    'content' => json_encode($toolResult),
    'cache_control' => ['type' => 'ephemeral'], // Cache file contents
];
```

**Impact**:

-   💰 90% cost reduction on subsequent queries (same codebase)
-   ⚡ 2-3x faster responses (cache hits)
-   📊 Better unit economics = competitive pricing

**Metrics Tracking**:

```php
// Store in command_run.metrics
[
    'cache_creation_input_tokens' => $response['usage']['cache_creation_input_tokens'] ?? 0,
    'cache_read_input_tokens' => $response['usage']['cache_read_input_tokens'] ?? 0,
    'input_tokens' => $response['usage']['input_tokens'] ?? 0,
]
```

**Timeline**: 3 days (implement + measure savings)

---

### 3. PR Context Integration (Week 5-6)

**What**: When triggered on a PR, automatically include diff, description, and comments.

**Implementation**:

**File**: `app/Services/Commands/PullRequestContextService.php`

````php
final readonly class PullRequestContextService
{
    public function __construct(
        private GitHubApiService $githubApi,
    ) {}

    /**
     * Build PR context for agent.
     */
    public function buildContext(CommandRun $commandRun): ?string
    {
        if (!$commandRun->is_pull_request) {
            return null;
        }

        // Fetch PR details
        $pr = $this->githubApi->getPullRequest(
            installationId: $commandRun->repository->installation->installation_id,
            owner: $commandRun->repository->owner,
            repo: $commandRun->repository->name,
            number: $commandRun->issue_number
        );

        // Fetch PR diff (changed files only)
        $diff = $this->githubApi->getPullRequestDiff(
            installationId: $commandRun->repository->installation->installation_id,
            owner: $commandRun->repository->owner,
            repo: $commandRun->repository->name,
            number: $commandRun->issue_number
        );

        // Fetch recent comments
        $comments = $this->githubApi->getPullRequestComments(
            installationId: $commandRun->repository->installation->installation_id,
            owner: $commandRun->repository->owner,
            repo: $commandRun->repository->name,
            number: $commandRun->issue_number,
            limit: 10
        );

        return $this->formatPRContext($pr, $diff, $comments);
    }

    private function formatPRContext(array $pr, string $diff, array $comments): string
    {
        $formattedComments = array_map(
            fn($c) => "- {$c['user']['login']}: {$c['body']}",
            array_slice($comments, 0, 10)
        );

        return <<<CTX
## Pull Request Context

**Title**: {$pr['title']}

**Description**:
{$pr['body']}

**Changed Files** ({$pr['changed_files']} files, +{$pr['additions']}/-{$pr['deletions']}):
```diff
{$this->truncateDiff($diff, 2000)} // Limit to 2000 chars
````

**Recent Comments**:
{implode("\n", $formattedComments)}

---

CTX;
}

    private function truncateDiff(string $diff, int $maxChars): string
    {
        if (strlen($diff) <= $maxChars) {
            return $diff;
        }

        return substr($diff, 0, $maxChars) . "\n\n... (diff truncated for brevity)";
    }

}

````

**Update CommandAgentService**:

```php
// In execute() method
public function execute(CommandRun $commandRun): array
{
    // ... existing code

    // NEW: Add PR context if applicable
    $prContext = app(PullRequestContextService::class)->buildContext($commandRun);

    if ($prContext) {
        $userMessage = $prContext . "\n\n" . $userMessage;
    }

    // ... rest of execution
}
````

**Impact**:

-   🎯 Agent understands what changed in the PR
-   🎯 Can review code in context of the changes
-   🎯 Answers are PR-specific, not generic
-   🎯 This is THE primary use case

**Timeline**: 2 days (implement + test on real PRs)

---

### 4. Incremental Indexing (Week 6)

**What**: Only index changed files on push, not entire repository.

**Implementation**:

**Update**: `app/Jobs/GitHub/ProcessPushWebhook.php`

```php
public function handle(CodeIndexingService $indexingService): void
{
    // ... existing PR review logic

    // NEW: Incremental indexing
    $repository = $this->getRepository();
    $commitSha = $this->payload['after'];

    // Extract changed files from commits
    $changedFiles = $this->extractChangedFiles();

    // Only index changed files (not entire repo)
    $indexingService->indexChangedFiles(
        repository: $repository,
        commitSha: $commitSha,
        changedFiles: $changedFiles
    );
}

private function extractChangedFiles(): array
{
    $added = [];
    $modified = [];
    $removed = [];

    // GitHub sends up to 20 commits in payload
    foreach ($this->payload['commits'] ?? [] as $commit) {
        $added = array_merge($added, $commit['added'] ?? []);
        $modified = array_merge($modified, $commit['modified'] ?? []);
        $removed = array_merge($removed, $commit['removed'] ?? []);
    }

    return [
        'added' => array_unique($added),
        'modified' => array_unique($modified),
        'removed' => array_unique($removed),
    ];
}
```

**Update**: `app/Services/CodeIndexing/CodeIndexingService.php`

```php
/**
 * Index only changed files (incremental).
 */
public function indexChangedFiles(
    Repository $repository,
    string $commitSha,
    array $changedFiles
): void {
    // 1. Delete indexes for removed files
    if (!empty($changedFiles['removed'])) {
        CodeIndex::where('repository_id', $repository->id)
            ->whereIn('file_path', $changedFiles['removed'])
            ->delete();
        // Cascades to embeddings via foreign key
    }

    // 2. Index added and modified files
    $filesToIndex = array_merge(
        $changedFiles['added'] ?? [],
        $changedFiles['modified'] ?? []
    );

    // Filter indexable files
    $indexableFiles = $this->filterIndexableFiles(
        array_map(fn($path) => ['path' => $path, 'type' => 'blob'], $filesToIndex)
    );

    // 3. Batch process
    foreach (array_chunk($indexableFiles, 50) as $batch) {
        IndexCodeBatchJob::dispatch($repository, $commitSha, $batch)
            ->onQueue(Queue::CodeIndexing->value);
    }
}
```

**Fallback for Large Pushes**:

```php
// If too many files changed, fall back to full index
if (count($filesToIndex) > 500) {
    Log::info("Large push detected, triggering full reindex", [
        'repository_id' => $repository->id,
        'changed_files' => count($filesToIndex),
    ]);

    $this->indexRepository($repository, $commitSha);
    return;
}
```

**Impact**:

-   ⚡ 95% faster indexing (seconds instead of minutes)
-   💰 90% lower embedding costs (only changed files)
-   🚀 Real-time freshness (indexes instantly)

**Timeline**: 2 days (implement + test)

---

## Implementation Timeline

### Week 1: Foundation - Data Models & Infrastructure

**Days 1-2**: Database & Models

-   Create migrations: `command_runs`, `code_indexes`, `code_embeddings`
-   Create models: `CommandRun`, `CodeIndex`, `CodeEmbedding`
-   Create enums: `CommandType`, `Queue::CodeIndexing`
-   Install pgvector: `composer require pgvector/pgvector`
-   Enable extension: `CREATE EXTENSION vector;`

**Days 3-5**: Code Indexing Infrastructure

-   Create `CodeIndexingService` with structure analysis
-   Create `IndexCodeBatchJob` and `GenerateCodeEmbeddingsJob`
-   Update `ProcessPushWebhook` to trigger indexing (full reindex)
-   Test indexing on sample repository
-   Verify embeddings created

**Deliverables**:

-   ✅ Database schema complete
-   ✅ Code indexing working (full reindex)
-   ✅ Embeddings generated and stored

---

### Week 2: Search & Agent Core

**Days 1-3**: Hybrid Search

-   Create `CodeSearchService` with keyword + semantic search
-   Implement keyword search (PostgreSQL queries)
-   Implement semantic search (pgvector similarity)
-   Implement result merging (60% keyword + 40% semantic)
-   Add 15-minute caching
-   Test search accuracy on indexed repo

**Days 4-5**: Agent Service

-   Create `CommandAgentService` with Prism tool calling
-   Implement tool definitions (search_code, read_file, get_file_structure)
-   Implement tool execution using search/index services
-   Implement agentic loop (max 10 iterations)
-   Test with mocked LLM responses

**Deliverables**:

-   ✅ Hybrid search returning relevant results
-   ✅ Agent can call tools and synthesize findings

---

### Week 3: Webhook & Command Execution

**Days 1-2**: Command Infrastructure

-   Create `CommandParser` service
-   Create `CommandPermissionService` and `CommandPermissionResult`
-   Create `ProcessIssueCommentWebhook` job
-   Update `GitHubWebhookController` to handle IssueComment
-   Test webhook with ngrok + real GitHub payloads

**Days 3-5**: Execution Flow

-   Create `CreateCommandRun` action
-   Create `ExecuteCommandRun` action
-   Create `ExecuteCommandRunJob`
-   Create `PostCommandResponse` action
-   Update `PlanLimitEnforcer` for command limits
-   Add `GitHubApiService::updateComment()` method

**Deliverables**:

-   ✅ @sentinel mentions trigger command execution
-   ✅ Agent explores codebase and posts response
-   ✅ Permissions enforced

---

### Week 4: Testing & Polish

**Days 1-2**: Testing

-   Unit tests: CommandParser, CommandPermissionService, CodeSearchService
-   Feature tests: CreateCommandRun, ExecuteCommandRun, IssueCommentWebhook
-   Integration tests: Full flow with mocked Prism responses
-   Test on real GitHub webhooks

**Days 3-4**: Optimization

-   Add database indexes for performance
-   Optimize search queries
-   Tune caching TTLs
-   Add monitoring metrics
-   Performance testing

**Day 5**: Deployment Prep

-   Deploy to staging
-   Test with real GitHub App
-   Monitor queue performance
-   Document for production

**Deliverables**:

-   ✅ Phase A complete and tested
-   ✅ Ready for Phase A+ enhancements

---

### Week 5: Phase A+ Critical Upgrades (Part 1)

**Days 1-2**: Extended Thinking

-   Add `withThinkingBudget(8192)` to agent Prism calls
-   Test on complex queries (architectural questions)
-   Measure quality improvement vs baseline
-   Document thinking token usage in metrics

**Days 3-4**: Prompt Caching

-   Add `cache_control` to system prompts
-   Add `cache_control` to large tool results (file contents)
-   Implement cache metrics tracking in CommandRun
-   Test cache hit rates
-   Measure cost savings (target: 90% reduction)

**Day 5**: PR Context Integration (Part 1)

-   Create `PullRequestContextService`
-   Implement PR details fetching (title, description, diff)
-   Implement PR comments fetching
-   Format PR context for agent

**Deliverables**:

-   ✅ Extended thinking enabled
-   ✅ Prompt caching reducing costs by 90%
-   ✅ PR context service ready

---

### Week 6: Phase A+ Critical Upgrades (Part 2)

**Days 1-2**: PR Context Integration (Part 2)

-   Integrate PR context into `CommandAgentService`
-   Test on real PRs with diffs
-   Verify agent understands PR context
-   Measure response quality improvement

**Days 3-4**: Incremental Indexing

-   Update `ProcessPushWebhook` to extract changed files
-   Implement `indexChangedFiles()` in CodeIndexingService
-   Handle file removals (cascade delete embeddings)
-   Test with various push sizes
-   Verify 95% speed improvement

**Day 5**: Final Testing & Documentation

-   End-to-end testing of all Phase A+ features
-   Performance benchmarking (search, indexing, response time)
-   Cost analysis (with caching)
-   Update documentation
-   Deploy to staging for beta testing

**Deliverables**:

-   ✅ Phase A+ complete
-   ✅ PR context integration working
-   ✅ Incremental indexing 95% faster
-   ✅ Ready for production launch

---

## Testing Strategy

### Unit Tests

**CommandParserTest**

-   Test @sentinel mention extraction
-   Test command type parsing
-   Test default to 'explain'
-   Test context hints extraction (files, symbols, line numbers)

**CommandPermissionServiceTest**

-   Test workspace membership validation
-   Test repository access validation
-   Test plan limit enforcement
-   Test BYOK requirement

**CodeSearchServiceTest**

-   Test keyword search
-   Test semantic search
-   Test result merging (60/40 split)
-   Test caching (15-minute TTL)

### Feature Tests

**CreateCommandRunTest**

-   Test command run creation from webhook
-   Test permission denials (non-member, no BYOK, plan limit)
-   Test plan limit enforcement

**ExecuteCommandRunTest**

-   Test full execution with mocked agent
-   Test error handling and retry
-   Test response posting to GitHub
-   Test task list updates

**IssueCommentWebhookTest**

-   Test webhook payload processing
-   Test @sentinel mention detection
-   Test non-command comments (ignored)
-   Test non-member handling

**CodeIndexingTest**

-   Test full repository indexing
-   Test incremental indexing (changed files only)
-   Test embedding generation
-   Test search on indexed data

### Integration Tests

**Full Flow Test**

```php
it('executes command from GitHub comment', function () {
    // 1. Setup
    $workspace = Workspace::factory()->create();
    $repo = Repository::factory()->for($workspace)->create();

    // Index sample code
    $indexingService = app(CodeIndexingService::class);
    $indexingService->indexFile($repo, 'abc123', 'app/Models/User.php', $userModelCode);

    // 2. Simulate webhook
    $payload = [
        'action' => 'created',
        'comment' => [
            'id' => 123,
            'body' => '@sentinel explain the is_active column',
        ],
        'issue' => [
            'number' => 1,
        ],
        'sender' => [
            'login' => 'testuser',
        ],
    ];

    // 3. Mock Prism responses
    Prism::fake([
        'search_code' => ['results' => [['file_path' => 'app/Models/User.php']]],
        'read_file' => ['content' => $userModelCode],
        'final_answer' => 'The is_active column...',
    ]);

    // 4. Process webhook
    ProcessIssueCommentWebhook::dispatch($payload);

    // 5. Assert
    $commandRun = CommandRun::latest()->first();
    expect($commandRun->status)->toBe('completed');
    expect($commandRun->response['answer'])->toContain('is_active');

    // GitHub comment should be posted
    $this->assertGitHubCommentPosted($repo, 1, $commandRun->response['answer']);
});
```

---

## Success Criteria

### Phase A Success (Foundation)

**Functional**:

-   ✅ @sentinel mentions trigger command execution
-   ✅ Agent uses production tools (search, read, structure)
-   ✅ Hybrid search combines keyword + semantic results
-   ✅ Responses include specific file paths and line numbers
-   ✅ Task checklist shows agent's work
-   ✅ Permissions prevent unauthorized access

**Performance**:

-   ✅ Indexing completes within 5 minutes for 10K file repo
-   ✅ Search returns results in <2 seconds (cached: <100ms)
-   ✅ Commands complete within 45 seconds (P95)
-   ✅ Agent uses <8 tool calls per command on average
-   ✅ >90% success rate for queries

**Quality**:

-   ✅ Responses match Claude quality in blind tests
-   ✅ Code examples are accurate and contextual
-   ✅ Explanations reference actual codebase structure
-   ✅ Semantic search finds conceptually related code

**Security**:

-   ✅ Only workspace members trigger commands
-   ✅ Plan limits enforced
-   ✅ BYOK ensures user pays AI costs
-   ✅ No unauthorized repository access

---

### Phase A+ Success (Competitive Advantages)

**Cost Efficiency**:

-   ✅ Prompt caching achieves 90% cost reduction on repeated context
-   ✅ Cache hit rate >70% after 1 week of usage
-   ✅ Average command cost <$0.02 (with caching)

**Performance**:

-   ✅ Incremental indexing completes in <30 seconds for typical push
-   ✅ Only 5-10% of files reindexed on average push
-   ✅ Response time 2-3x faster with prompt caching

**Quality**:

-   ✅ Extended thinking improves response quality (measured via user ratings)
-   ✅ PR context integration: agent references PR diff in responses
-   ✅ >95% user satisfaction on PR-specific queries

**Metrics Dashboard**:

```php
// Track in CommandRun.metrics
[
    // Extended Thinking
    'thinking_tokens' => 2048,

    // Prompt Caching
    'cache_creation_input_tokens' => 50000,
    'cache_read_input_tokens' => 50000,
    'input_tokens' => 5000,
    'cache_hit_rate' => 0.91, // 91%
    'cost_savings' => 0.90, // 90% saved

    // Incremental Indexing
    'files_indexed' => 12,
    'files_total' => 8432,
    'index_percentage' => 0.14, // 0.14%

    // PR Context
    'pr_context_included' => true,
    'pr_diff_size' => 1843,
]
```

---

## Future Scope

The following features are documented for post-Phase A+ implementation. They are not part of the initial 6-week timeline but represent the roadmap for market leadership.

### Phase B: Competitive Differentiation (4 weeks)

**Multi-turn Conversations** (4 days)

-   Allow follow-up questions without @sentinel mention
-   Detect replies to Sentinel's comments
-   Include conversation history in context

**Cross-File Understanding** (1 week)

-   Extract imports, dependencies, call graphs
-   Build dependency graph during indexing
-   New tool: `find_usages` for cross-file analysis

**Response Streaming** (3 days)

-   Stream agent's thinking in real-time
-   Update GitHub comment as agent works
-   Show tool calls as they happen

**Multi-Repository Context** (1 week)

-   Search across multiple repos in workspace
-   Cross-repo dependency analysis
-   Enterprise feature for microservices

---

### Phase C: Market Dominance (4 weeks)

**Codebase Learning/Memory** (2 weeks) ⭐ **MOAT**

-   Remember architecture patterns per codebase
-   Learn team conventions over time
-   Store as embeddings in `codebase_memory` table
-   Agent gets uniquely valuable per team

**Test Coverage Integration** (1 week)

-   Link source files to test files
-   Suggest relevant tests
-   Analyze coverage gaps
-   Quality-focused differentiation

**Historical Context** (1 week)

-   Include past PR discussions
-   Reference previous issues
-   Learn from commit messages
-   Institutional knowledge

---

### Competitive Analysis

**vs GitHub Copilot Chat**:

-   ✅ Better context (full codebase indexed vs IDE-only)
-   ✅ Better search (hybrid semantic + keyword)
-   ✅ Shows work (task checklist visible)
-   ✅ Cross-file understanding

**vs Cursor**:

-   ✅ GitHub native (lives where reviews happen)
-   ✅ Team context (shared across team)
-   ✅ No context limits (full repo indexed)
-   ✅ BYOK (users control costs)
-   ✅ Lower pricing ($30 vs $40/user/month)

**vs Claude in GitHub**:

-   ✅ Faster (pre-indexed vs on-demand)
-   ✅ Cached (90% cost reduction)
-   ✅ Deeper (extended thinking always on)
-   ✅ PR-optimized (auto-includes context)
-   ✅ Smarter over time (codebase memory in Phase C)

---

### Long-term Moat

The **codebase memory system** (Phase C) creates a defensible data moat:

1. Agent learns patterns unique to each codebase
2. Becomes uniquely valuable to each team
3. Creates dataset flywheel (more usage = smarter)
4. High switching cost once invested

**This + extended thinking + prompt caching = unbeatable combination.**

---

## Appendices

### A. File Structure

```
app/
├── Actions/
│   └── Commands/
│       ├── CreateCommandRun.php
│       ├── ExecuteCommandRun.php
│       └── PostCommandResponse.php
├── Enums/
│   ├── CommandType.php
│   └── Queue.php (add CodeIndexing)
├── Jobs/
│   ├── Commands/
│   │   └── ExecuteCommandRunJob.php
│   ├── CodeIndexing/
│   │   ├── IndexCodeBatchJob.php
│   │   └── GenerateCodeEmbeddingsJob.php
│   └── GitHub/
│       ├── ProcessIssueCommentWebhook.php (new)
│       └── ProcessPushWebhook.php (updated)
├── Models/
│   ├── CommandRun.php
│   ├── CodeIndex.php
│   └── CodeEmbedding.php
└── Services/
    ├── CodeIndexing/
    │   ├── CodeIndexingService.php
    │   └── CodeSearchService.php
    └── Commands/
        ├── CommandAgentService.php
        ├── CommandParser.php
        ├── CommandPermissionService.php
        ├── CommandPermissionResult.php
        └── PullRequestContextService.php (Phase A+)
```

### B. Configuration

**Queue Configuration** (`config/horizon.php`):

```php
'environments' => [
    'production' => [
        'code-indexing' => [
            'connection' => 'redis',
            'queue' => ['code-indexing'],
            'balance' => 'auto',
            'processes' => 3, // Parallel indexing
            'tries' => 2,
            'timeout' => 300, // 5 minutes
        ],
    ],
],
```

**Plan Limits** (extend in database seeders):

```php
'commands_per_month' => [
    'starter' => 100,
    'pro' => 500,
    'enterprise' => 'unlimited',
],
```

### C. Monitoring & Alerts

**Key Metrics to Track**:

-   Command run success rate (target: >95%)
-   Average response time (target: <45s P95)
-   Search latency (target: <2s uncached, <100ms cached)
-   Indexing time per file (target: <500ms P95)
-   Cache hit rate (target: >70%)
-   Cost per command (target: <$0.02 with caching)

**Alerts**:

-   Command run failure rate >5%
-   Indexing backlog >1000 files
-   Cache hit rate <50%
-   Average response time >60s

---

## Version History

**v1.0** - 2026-01-19

-   Initial plan with Phase A + Phase A+ integration
-   PostgreSQL pgvector selected
-   6-week timeline (4 weeks + 2 weeks)
-   Extended thinking, prompt caching, PR context, incremental indexing
-   Future scope documented (Phase B & C)

---

**Document Owner**: Engineering Team
**Reviewers**: Product, DevOps
**Next Review**: After Phase A completion (Week 4)

1. @sentinel review on a PR → Creates a Run and dispatches full automated review
2. @sentinel review on an issue → Creates a CommandRun and uses the agent-based command flow (since there's no PR diff to review)
3. Permissions → Same workspace membership check as other commands
4. Auto-review disabled → Returns error message asking user to enable in settings
