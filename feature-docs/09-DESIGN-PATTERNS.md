# Design Patterns in Sentinel

## Patterns That Power Enterprise-Grade Software

Great software isn't just about features—it's about how those features are built. Sentinel employs battle-tested design patterns that make the codebase maintainable, testable, and scalable. This document catalogs every major pattern used and explains why each was chosen.

---

## Pattern Overview

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     DESIGN PATTERNS AT A GLANCE                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Architectural Patterns                                                          │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐               │
│  │   Action    │ │   Service   │ │   Event-    │ │  Multi-     │               │
│  │   Pattern   │ │   Layer     │ │   Driven    │ │  Tenant     │               │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘               │
│                                                                                  │
│  Behavioral Patterns                                                             │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐               │
│  │  Chain of   │ │   State     │ │  Strategy   │ │  Observer   │               │
│  │Responsibility│ │  Machine    │ │   Pattern   │ │   Pattern   │               │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘               │
│                                                                                  │
│  Creational Patterns                                                            │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐                               │
│  │   Builder   │ │   Factory   │ │  Singleton  │                               │
│  │   Pattern   │ │   Pattern   │ │   Pattern   │                               │
│  └─────────────┘ └─────────────┘ └─────────────┘                               │
│                                                                                  │
│  Structural Patterns                                                            │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐               │
│  │    DTO      │ │   Adapter   │ │  Decorator  │ │  Middleware │               │
│  │   Pattern   │ │   Pattern   │ │   Pattern   │ │   Pattern   │               │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘               │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Action Pattern

**The cornerstone of Sentinel's architecture.**

Every business operation in Sentinel flows through an Action class. This isn't just organizational preference—it's an architectural mandate that ensures testability, reusability, and clear ownership of business logic.

### Pattern Definition

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     ACTION PATTERN FLOW                                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│       HTTP Request                                                              │
│            │                                                                     │
│            ▼                                                                     │
│  ┌──────────────────┐                                                           │
│  │    Controller    │  ← Thin, no business logic                               │
│  │   (validates)    │                                                           │
│  └────────┬─────────┘                                                           │
│           │                                                                      │
│           ▼                                                                      │
│  ┌──────────────────┐                                                           │
│  │     Action       │  ← Single responsibility                                  │
│  │  (orchestrates)  │  ← Depends on contracts/interfaces                        │
│  └────────┬─────────┘  ← Unit of business-flow testing                         │
│           │                                                                      │
│     ┌─────┴─────┬─────────┬─────────┐                                          │
│     ▼           ▼         ▼         ▼                                          │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐                                   │
│  │Service │ │  Job   │ │ Event  │ │ Model  │                                   │
│  └────────┘ └────────┘ └────────┘ └────────┘                                   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Key Locations

| Action Domain | Location | Count |
|---------------|----------|-------|
| Reviews | `app/Actions/Reviews/` | 15+ |
| Briefings | `app/Actions/Briefings/` | 10+ |
| Commands | `app/Actions/Commands/` | 8+ |
| Workspaces | `app/Actions/Workspaces/` | 12+ |
| Teams | `app/Actions/Teams/` | 6+ |
| Subscriptions | `app/Actions/Subscriptions/` | 4+ |
| Repositories | `app/Actions/Repositories/` | 8+ |
| Installations | `app/Actions/Installations/` | 5+ |
| Activities | `app/Actions/Activities/` | 2+ |
| Provider Keys | `app/Actions/ProviderKeys/` | 4+ |

### Example: ProcessPullRequestReview

```php
// app/Actions/Reviews/ProcessPullRequestReview.php
final readonly class ProcessPullRequestReview
{
    public function __construct(
        private CreateRun $createRun,                    // Other Action
        private ContextEngine $contextEngine,            // Service
        private ReviewEngine $reviewEngine,              // Service
        private PostReviewAnnotations $postAnnotations,  // Action
    ) {}

    public function handle(
        Workspace $workspace,
        Repository $repository,
        PullRequestData $pullRequest,
    ): Run {
        // 1. Create run record
        $run = $this->createRun->handle($workspace, $repository, $pullRequest);

        // 2. Build context
        $context = $this->contextEngine->build($repository, $pullRequest);

        // 3. Execute AI review
        $result = $this->reviewEngine->review($context);

        // 4. Post annotations
        $this->postAnnotations->handle($run, $result);

        return $run;
    }
}
```

### Why This Pattern?

- **Testability**: Actions are easy to unit test with mocked dependencies
- **Reusability**: Same action callable from controllers, jobs, commands
- **Clarity**: One action = one use case = clear responsibility
- **Composability**: Actions can call other actions

---

## 2. Service Layer Pattern

**Encapsulating focused, domain-specific logic.**

While Actions orchestrate workflows, Services contain the actual implementation logic. They're stateless, focused, and reusable across multiple Actions.

### Service Categories

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     SERVICE LAYER ORGANIZATION                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  app/Services/                                                                  │
│  │                                                                               │
│  ├── AI/                    ← AI provider integration                           │
│  │   ├── Contracts/         ← Interfaces for providers                          │
│  │   ├── ProviderRouter.php                                                     │
│  │   └── Prism/                                                                 │
│  │                                                                               │
│  ├── Context/               ← Context building for reviews                      │
│  │   ├── ContextEngine.php                                                      │
│  │   ├── Collectors/        ← Chain of Responsibility                           │
│  │   └── Filters/           ← Content filtering                                 │
│  │                                                                               │
│  ├── Review/                ← Review execution                                  │
│  │   ├── ReviewEngine.php                                                       │
│  │   └── FindingProcessor.php                                                   │
│  │                                                                               │
│  ├── Commands/              ← Command processing                                │
│  │   ├── CommandAgentService.php                                                │
│  │   └── Tools/             ← Tool implementations                              │
│  │                                                                               │
│  ├── GitHub/                ← GitHub API integration                            │
│  │   ├── GitHubApiService.php                                                   │
│  │   └── WebhookProcessor.php                                                   │
│  │                                                                               │
│  ├── Plans/                 ← Plan enforcement                                  │
│  │   └── PlanLimitEnforcer.php                                                  │
│  │                                                                               │
│  └── Briefings/             ← Briefing generation                               │
│      ├── DataCollectors/                                                        │
│      └── NarrativeGenerator.php                                                 │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Example: ContextEngine

```php
// app/Services/Context/ContextEngine.php
final class ContextEngine
{
    /** @var array<ContextCollector> */
    private array $collectors;

    /** @var array<ContextFilter> */
    private array $filters;

    public function build(Repository $repo, PullRequestData $pr): ReviewContext
    {
        $context = new ReviewContext();

        // Chain of collectors builds context
        foreach ($this->collectors as $collector) {
            $context = $collector->collect($context, $repo, $pr);
        }

        // Chain of filters refines context
        foreach ($this->filters as $filter) {
            $context = $filter->filter($context);
        }

        return $context;
    }
}
```

---

## 3. Event-Driven Architecture

**Decoupling side effects from core logic.**

Sentinel uses Laravel's event system extensively to decouple side effects (notifications, logging, metrics) from primary business logic.

### Event Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     EVENT-DRIVEN FLOW                                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Action: CompleteRun                                                            │
│           │                                                                      │
│           ├── Save run to database                                              │
│           │                                                                      │
│           └── Dispatch event: RunCompleted                                      │
│                        │                                                         │
│           ┌────────────┼────────────┬────────────────┐                          │
│           ▼            ▼            ▼                ▼                          │
│      ┌─────────┐ ┌─────────┐ ┌───────────┐ ┌──────────────┐                    │
│      │ Notify  │ │  Log    │ │  Update   │ │  Broadcast   │                    │
│      │  User   │ │Activity │ │  Metrics  │ │  WebSocket   │                    │
│      └─────────┘ └─────────┘ └───────────┘ └──────────────┘                    │
│                                                                                  │
│  Benefits:                                                                       │
│  • Action doesn't know about listeners                                          │
│  • Easy to add new side effects                                                 │
│  • Listeners can be queued for performance                                      │
│  • Testing focuses on core logic                                                │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Event Locations

| Event Category | Location | Examples |
|----------------|----------|----------|
| Review Events | `app/Events/Reviews/` | `RunStarted`, `RunCompleted`, `RunFailed` |
| Briefing Events | `app/Events/Briefings/` | `BriefingGenerationStarted`, `BriefingGenerationCompleted` |
| Command Events | `app/Events/Commands/` | `CommandRunStarted`, `CommandRunCompleted` |
| Workspace Events | `app/Events/Workspaces/` | `WorkspaceCreated`, `MemberInvited` |
| Installation Events | `app/Events/Installations/` | `InstallationConnected`, `InstallationSuspended` |

### Broadcast Events (WebSocket)

```php
// app/Events/Briefings/BriefingGenerationProgress.php
class BriefingGenerationProgress implements ShouldBroadcast
{
    public function __construct(
        public int $generationId,
        public int $progress,
        public string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->workspaceId}.briefings"),
        ];
    }
}
```

---

## 4. Chain of Responsibility

**Building context through modular collectors.**

The ContextEngine uses Chain of Responsibility to build review context through specialized collectors, each adding its piece to the puzzle.

### Collector Chain

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     CONTEXT COLLECTOR CHAIN                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Empty Context                                                                   │
│       │                                                                          │
│       ▼                                                                          │
│  ┌─────────────────────┐                                                        │
│  │  DiffCollector      │  → Adds file diffs from PR                            │
│  └──────────┬──────────┘                                                        │
│             │                                                                    │
│             ▼                                                                    │
│  ┌─────────────────────┐                                                        │
│  │  FileTreeCollector  │  → Adds repository file structure                     │
│  └──────────┬──────────┘                                                        │
│             │                                                                    │
│             ▼                                                                    │
│  ┌─────────────────────┐                                                        │
│  │ DependencyCollector │  → Adds dependency context (package.json, etc)        │
│  └──────────┬──────────┘                                                        │
│             │                                                                    │
│             ▼                                                                    │
│  ┌─────────────────────┐                                                        │
│  │ GuidelinesCollector │  → Adds team coding guidelines                         │
│  └──────────┬──────────┘                                                        │
│             │                                                                    │
│             ▼                                                                    │
│  ┌─────────────────────┐                                                        │
│  │ HistoryCollector    │  → Adds git history context                           │
│  └──────────┬──────────┘                                                        │
│             │                                                                    │
│             ▼                                                                    │
│  Rich Context (ready for AI)                                                    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Collector Interface

```php
// app/Services/Context/Contracts/ContextCollector.php
interface ContextCollector
{
    public function collect(
        ReviewContext $context,
        Repository $repository,
        PullRequestData $pullRequest,
    ): ReviewContext;

    public function priority(): int;
}
```

### Collector Locations

| Collector | Purpose |
|-----------|---------|
| `DiffContextCollector` | Collects file diffs |
| `FileTreeContextCollector` | Collects repo structure |
| `DependencyContextCollector` | Collects package info |
| `GuidelinesContextCollector` | Collects team guidelines |
| `HistoryContextCollector` | Collects git history |

---

## 5. State Machine Pattern

**Managing complex entity lifecycles.**

Runs and Briefing Generations follow state machine patterns with explicit transitions and guards.

### Run State Machine

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     RUN STATE MACHINE                                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│                              ┌───────────────────┐                              │
│                              │                   │                              │
│        ┌─────────────────────►     skipped      │                              │
│        │                     │                   │                              │
│        │                     └───────────────────┘                              │
│        │ (triggers not                                                           │
│        │   matched)                                                              │
│        │                                                                         │
│  ┌─────┴─────┐    start()    ┌───────────────┐    complete()   ┌───────────┐   │
│  │           ├──────────────►│               ├────────────────►│           │   │
│  │  queued   │               │  in_progress  │                 │ completed │   │
│  │           │◄──────────────┤               │◄────────────────┤           │   │
│  └───────────┘    retry()    └───────┬───────┘    retry()      └───────────┘   │
│                                      │                                          │
│                                      │ fail()                                   │
│                                      ▼                                          │
│                              ┌───────────────┐                                  │
│                              │    failed     │                                  │
│                              └───────────────┘                                  │
│                                                                                  │
│  Terminal states: completed, failed, skipped                                    │
│  Retriable states: queued, in_progress                                          │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Status Enum

```php
// app/Enums/Reviews/RunStatus.php
enum RunStatus: string
{
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Skipped,
        ]);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Queued => in_array($target, [self::InProgress, self::Skipped]),
            self::InProgress => in_array($target, [self::Completed, self::Failed]),
            self::Failed => $target === self::Queued, // retry
            default => false,
        };
    }
}
```

---

## 6. Data Transfer Object (DTO) Pattern

**Type-safe data passing between layers.**

DTOs ensure data integrity and provide explicit contracts between system components.

### DTO Categories

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     DTO ORGANIZATION                                             │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  app/DataTransferObjects/                                                       │
│  │                                                                               │
│  ├── GitHub/                                                                    │
│  │   ├── PullRequestData.php         ← PR webhook payload                       │
│  │   ├── InstallationData.php        ← Installation event data                  │
│  │   ├── CheckSuiteData.php          ← Check suite data                         │
│  │   └── IssueCommentData.php        ← Comment event data                       │
│  │                                                                               │
│  ├── Reviews/                                                                   │
│  │   ├── ReviewContextData.php       ← Context for AI review                    │
│  │   ├── ReviewResultData.php        ← AI response structure                    │
│  │   └── FindingData.php             ← Individual finding                       │
│  │                                                                               │
│  ├── Commands/                                                                  │
│  │   ├── CommandRequestData.php      ← Parsed command request                   │
│  │   └── CommandResponseData.php     ← Command execution result                 │
│  │                                                                               │
│  └── SentinelConfig/                                                            │
│      └── SentinelConfigData.php      ← Parsed config file                       │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Example DTO

```php
// app/DataTransferObjects/GitHub/PullRequestData.php
final readonly class PullRequestData
{
    public function __construct(
        public int $number,
        public string $title,
        public string $headBranch,
        public string $baseBranch,
        public string $headSha,
        public string $baseSha,
        public string $authorLogin,
        public string $state,
        public bool $isDraft,
        public array $labels,
        public ?string $body,
    ) {}

    public static function fromWebhook(array $payload): self
    {
        $pr = $payload['pull_request'];

        return new self(
            number: $pr['number'],
            title: $pr['title'],
            headBranch: $pr['head']['ref'],
            baseBranch: $pr['base']['ref'],
            headSha: $pr['head']['sha'],
            baseSha: $pr['base']['sha'],
            authorLogin: $pr['user']['login'],
            state: $pr['state'],
            isDraft: $pr['draft'] ?? false,
            labels: array_column($pr['labels'] ?? [], 'name'),
            body: $pr['body'],
        );
    }
}
```

---

## 7. Strategy Pattern

**Interchangeable algorithms at runtime.**

The Strategy pattern appears throughout Sentinel where behavior needs to be swapped based on context.

### AI Provider Strategy

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     AI PROVIDER STRATEGY                                         │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌───────────────────────────────────────────────────────────────────────────┐ │
│  │  interface AIProvider                                                      │ │
│  │  ──────────────────────                                                   │ │
│  │  + generateReview(context: ReviewContext): ReviewResult                   │ │
│  │  + generateResponse(query: string, context: array): string               │ │
│  │  + generateBriefing(data: BriefingData): string                          │ │
│  └───────────────────────────────────────────────────────────────────────────┘ │
│                    △                          △                                 │
│                    │                          │                                 │
│         ┌─────────┴─────────┐      ┌─────────┴─────────┐                       │
│         │                    │      │                    │                       │
│  ┌──────────────────┐  ┌──────────────────┐                                    │
│  │ AnthropicProvider │  │  OpenAIProvider  │                                    │
│  │ ────────────────── │  │ ──────────────── │                                    │
│  │ Uses Claude API    │  │ Uses GPT-4 API   │                                    │
│  └──────────────────┘  └──────────────────┘                                    │
│                                                                                  │
│  ProviderRouter selects based on:                                               │
│  • Workspace's configured provider key                                          │
│  • Model preference settings                                                    │
│  • Fallback configuration                                                       │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Annotation Strategy

```php
// Different strategies for posting review feedback
interface AnnotationStrategy
{
    public function post(Run $run, array $findings): void;
}

class ReviewCommentStrategy implements AnnotationStrategy { }
class CheckRunStrategy implements AnnotationStrategy { }
class IssueCommentStrategy implements AnnotationStrategy { }
```

---

## 8. Builder Pattern

**Complex object construction with fluent APIs.**

The Builder pattern appears where objects require multi-step construction.

### Context Builder

```php
// app/Services/Context/ReviewContextBuilder.php
final class ReviewContextBuilder
{
    private array $files = [];
    private array $dependencies = [];
    private ?string $guidelines = null;
    private ?array $history = null;

    public function withFiles(array $files): self
    {
        $this->files = $files;
        return $this;
    }

    public function withDependencies(array $deps): self
    {
        $this->dependencies = $deps;
        return $this;
    }

    public function withGuidelines(string $guidelines): self
    {
        $this->guidelines = $guidelines;
        return $this;
    }

    public function withHistory(array $history): self
    {
        $this->history = $history;
        return $this;
    }

    public function build(): ReviewContext
    {
        return new ReviewContext(
            files: $this->files,
            dependencies: $this->dependencies,
            guidelines: $this->guidelines,
            history: $this->history,
        );
    }
}
```

---

## 9. Adapter Pattern

**Bridging external APIs to internal interfaces.**

The Adapter pattern isolates external system specifics from core domain logic.

### GitHub Adapter

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     ADAPTER PATTERN                                              │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  External World                     Sentinel Core                               │
│  ─────────────                     ─────────────                                │
│                                                                                  │
│  ┌──────────────┐                 ┌──────────────────────────────────────────┐ │
│  │              │    adapts       │                                          │ │
│  │ GitHub API   │ ─────────────►  │  SourceControlProvider interface        │ │
│  │              │                 │                                          │ │
│  └──────────────┘                 │  + getFile(path): string                 │ │
│                                    │  + getDiff(pr): Diff                     │ │
│  ┌──────────────┐                 │  + postComment(pr, body): void           │ │
│  │              │    adapts       │  + createCheckRun(...): void             │ │
│  │ GitLab API   │ ─────────────►  │                                          │ │
│  │  (future)    │                 └──────────────────────────────────────────┘ │
│  └──────────────┘                                                               │
│                                                                                  │
│  Implementation:                                                                │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │  GitHubApiService                                                        │   │
│  │  ─────────────────                                                       │   │
│  │  Uses GitHub REST/GraphQL APIs internally                                │   │
│  │  Exposes SourceControlProvider interface externally                      │   │
│  │  Translates GitHub-specific responses to domain objects                  │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 10. Observer Pattern (via Events)

**Reacting to state changes across the system.**

Laravel's event system implements the Observer pattern, allowing components to react to changes without tight coupling.

### Event Listeners

| Event | Listeners |
|-------|-----------|
| `RunCompleted` | `UpdateDailyMetrics`, `NotifyUser`, `LogActivity` |
| `WorkspaceCreated` | `CreateDefaultTeam`, `LogActivity` |
| `InstallationConnected` | `SyncRepositories`, `NotifyOwner` |
| `CommandRunCompleted` | `UpdateCommandMetrics`, `LogActivity` |

---

## 11. Repository Pattern

**Abstracting data access logic.**

While Eloquent provides much of this, Sentinel uses custom query methods on models that act as repositories.

```php
// app/Models/Run.php
class Run extends Model
{
    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->where('workspace_id', $workspace->id);
    }

    public function scopeInPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RunStatus::Completed);
    }
}
```

---

## 12. Middleware Pattern

**Request/response pipeline processing.**

Beyond HTTP middleware, Sentinel uses the middleware concept for processing pipelines.

### HTTP Middleware

| Middleware | Purpose |
|------------|---------|
| `EnsureWorkspaceMember` | Validates workspace access |
| `EnsureActiveSubscription` | Checks subscription status |
| `ValidateWebhookSignature` | Verifies GitHub signatures |
| `ThrottleRequests` | Rate limiting |

### Processing Middleware (Conceptual)

```php
// Context processing uses a pipeline pattern
$context = Pipeline::send($rawContext)
    ->through([
        CollectDiffs::class,
        CollectDependencies::class,
        CollectGuidelines::class,
        FilterSensitiveContent::class,
        TokenLimitEnforcer::class,
    ])
    ->thenReturn();
```

---

## 13. Value Object Pattern

**Immutable objects representing domain concepts.**

```php
// app/Services/Plans/ValueObjects/BillingPeriod.php
final readonly class BillingPeriod
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {}

    public static function currentMonth(): self
    {
        return new self(
            start: now()->startOfMonth(),
            end: now()->endOfMonth(),
        );
    }

    public function contains(Carbon $date): bool
    {
        return $date->between($this->start, $this->end);
    }
}
```

---

## 14. Singleton Pattern

**Single instance services.**

Laravel's service container handles singletons for services that should have only one instance.

```php
// bootstrap/providers.php or ServiceProvider
$this->app->singleton(ContextEngine::class);
$this->app->singleton(ReviewEngine::class);
$this->app->singleton(PlanLimitEnforcer::class);
```

---

## 15. Factory Pattern

**Creating complex objects with context.**

Laravel's model factories are used extensively for testing, but the pattern also appears in domain logic.

### Model Factories

| Factory | Creates |
|---------|---------|
| `UserFactory` | Test users |
| `WorkspaceFactory` | Test workspaces with plans |
| `RepositoryFactory` | Test repos with installations |
| `RunFactory` | Runs with various statuses |
| `FindingFactory` | Findings with severities |

### Domain Factories

```php
// Creating findings from AI response
final class FindingFactory
{
    public static function fromAIResponse(array $raw, Run $run): Finding
    {
        return new Finding([
            'run_id' => $run->id,
            'workspace_id' => $run->workspace_id,
            'category' => FindingCategory::from($raw['category']),
            'severity' => FindingSeverity::from($raw['severity']),
            'title' => $raw['title'],
            'description' => $raw['description'],
            'file_path' => $raw['file'] ?? null,
            'line_start' => $raw['line_start'] ?? null,
            'line_end' => $raw['line_end'] ?? null,
        ]);
    }
}
```

---

## Pattern Summary Table

| Pattern | Primary Use | Key Locations |
|---------|-------------|---------------|
| Action | Business orchestration | `app/Actions/` |
| Service Layer | Focused logic | `app/Services/` |
| Event-Driven | Side effect decoupling | `app/Events/`, `app/Listeners/` |
| Chain of Responsibility | Context building | `app/Services/Context/Collectors/` |
| State Machine | Entity lifecycle | `app/Enums/*Status.php` |
| DTO | Data transfer | `app/DataTransferObjects/` |
| Strategy | Swappable algorithms | `app/Services/AI/` |
| Builder | Complex construction | `*Builder.php` classes |
| Adapter | External integration | `app/Services/GitHub/` |
| Observer | Event reaction | Laravel Events |
| Repository | Data access | Model scopes |
| Middleware | Pipeline processing | `app/Http/Middleware/` |
| Value Object | Immutable values | `app/Services/*/ValueObjects/` |
| Singleton | Single instances | Service container bindings |
| Factory | Object creation | `database/factories/` |

---

## Why These Patterns Matter

1. **Maintainability**: Clear separation of concerns makes code easier to modify
2. **Testability**: Patterns like Action and Strategy make unit testing straightforward
3. **Scalability**: Event-driven architecture allows horizontal scaling
4. **Flexibility**: Strategy and Adapter patterns enable easy integration changes
5. **Onboarding**: Consistent patterns help new developers understand the codebase

---

*This document is a living reference. As Sentinel evolves, new patterns may be introduced and documented here.*
