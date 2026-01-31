# @sentinel Commands

## Your AI Pair Programmer in PRs

Ever wished you could just *ask* someone about the code you're reviewing? With @sentinel commands, you can. Mention `@sentinel` in a PR comment, and you've got an AI assistant ready to help—search the codebase, explain code, find related implementations, and more.

Think of it as having a senior engineer available 24/7, one who has read every file in your repository and remembers it all.

---

## How It Works

### The Basics

Post a comment on any PR:

```
@sentinel search for authentication middleware
```

Sentinel will:
1. Recognize the mention
2. Parse your command
3. Execute the appropriate tools
4. Post a response as a PR comment

### Under the Hood

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        @SENTINEL COMMAND FLOW                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. USER POSTS COMMENT                                                          │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  @sentinel what does the validateInput function do?                    │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  2. GITHUB WEBHOOK                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Event: issue_comment.created                                           │    │
│  │  Action: Check if body contains @sentinel mention                       │    │
│  │  Result: Dispatch ProcessIssueCommentWebhook job                        │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  3. COMMAND PARSING                                                             │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  CommandParser extracts:                                                │    │
│  │  • Command type: "explain" (inferred from "what does ... do")          │    │
│  │  • Query: "validateInput function"                                      │    │
│  │  • Context hints: function name, possible file paths                   │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  4. PERMISSION CHECK                                                            │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  CommandPermissionService verifies:                                     │    │
│  │  ✓ User has access to repository                                       │    │
│  │  ✓ Command type is allowed for this repo                               │    │
│  │  ✓ Path rules permit access to relevant files                          │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  5. COMMAND EXECUTION                                                           │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  CommandAgentService:                                                   │    │
│  │  • Builds context from repository                                       │    │
│  │  • Selects appropriate tools (search, read, symbol find)               │    │
│  │  • Constructs prompt for AI                                            │    │
│  │  • Executes AI call with tool use capability                           │    │
│  │  • Parses response                                                     │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  6. RESPONSE POSTED                                                             │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  PostCommandResponse creates GitHub comment:                            │    │
│  │                                                                          │    │
│  │  "The `validateInput` function in `app/Services/InputService.php`      │    │
│  │   (lines 45-78) sanitizes user input before processing.                │    │
│  │                                                                          │    │
│  │   Key responsibilities:                                                 │    │
│  │   - Strips HTML tags from string inputs                                │    │
│  │   - Validates email format using RFC 5322                              │    │
│  │   - Enforces maximum length constraints                                │    │
│  │   ..."                                                                  │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Available Commands

### Search Command

Find code across your repository:

```
@sentinel search for rate limiting implementation
@sentinel search for functions that handle user authentication
@sentinel search for classes that implement PaymentProcessor
```

**What it does:**
- Searches file contents using semantic understanding
- Finds relevant code even if you don't know exact names
- Returns file paths, line numbers, and context

### Explain Command

Understand what code does:

```
@sentinel explain the processPayment method in PaymentService
@sentinel what does this file do? [link to file]
@sentinel explain the flow from checkout to order confirmation
```

**What it does:**
- Reads the relevant code
- Provides clear, human-readable explanation
- Highlights key logic and edge cases

### Find Symbol Command

Locate specific code elements:

```
@sentinel find the User model
@sentinel where is the handleWebhook function defined
@sentinel find all usages of the CacheService class
```

**What it does:**
- Locates class/function/method definitions
- Shows file paths and line numbers
- Can find usages across the codebase

### Review Command

Trigger a manual review:

```
@sentinel review
@sentinel review this PR
```

**What it does:**
- Triggers a full automated review
- Works even if auto-review is disabled
- Uses your configured review settings

### Help Command

Get help with commands:

```
@sentinel help
@sentinel what can you do?
```

---

## Command Tools

Under the hood, @sentinel commands use specialized tools to interact with your codebase:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          COMMAND TOOLS                                           │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  🔍 CODE SEARCH TOOL                                                            │
│  ├─ Semantic search across files                                                │
│  ├─ Understands natural language queries                                        │
│  └─ Returns relevant code snippets with context                                 │
│                                                                                  │
│  📖 FILE READ TOOL                                                              │
│  ├─ Reads specific files or ranges                                              │
│  ├─ Respects path rules (can't read sensitive files)                           │
│  └─ Returns contents with line numbers                                         │
│                                                                                  │
│  🎯 SYMBOL FIND TOOL                                                            │
│  ├─ Locates classes, functions, methods                                         │
│  ├─ Uses semantic code analysis                                                 │
│  └─ Returns definitions and usages                                              │
│                                                                                  │
│  📂 DIRECTORY LIST TOOL                                                         │
│  ├─ Lists files in a directory                                                  │
│  ├─ Helps understand project structure                                          │
│  └─ Can filter by patterns                                                      │
│                                                                                  │
│  📊 GREP TOOL                                                                   │
│  ├─ Pattern matching across files                                               │
│  ├─ Supports regex                                                              │
│  └─ Returns matching lines with context                                         │
│                                                                                  │
│  📝 DIFF CONTEXT TOOL                                                           │
│  ├─ Gets the PR diff                                                            │
│  ├─ Understands what changed                                                    │
│  └─ Provides context for questions about the PR                                │
│                                                                                  │
│  💡 EXPLAIN CODE TOOL                                                           │
│  ├─ Generates explanations for code                                             │
│  ├─ Identifies patterns and design decisions                                    │
│  └─ Highlights potential issues                                                 │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Path Rules & Security

Not all files are accessible via @sentinel commands. This is intentional:

### Default Restrictions

```
# These patterns are blocked by default:
.env*
**/secrets/**
**/credentials/**
**/.ssh/**
**/*.pem
**/*.key
```

### Configurable Path Rules

In your `.sentinel/config.yaml`:

```yaml
commands:
  paths:
    allow:
      - "app/**"
      - "src/**"
      - "lib/**"
    deny:
      - "**/migrations/**"
      - "**/seeds/**"
```

### Permission Checks

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        COMMAND PERMISSION FLOW                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Request to read: app/Services/AuthService.php                                  │
│                                                                                  │
│  Step 1: Is this a sensitive file pattern?                                      │
│          └─ Check against default deny patterns                                 │
│          └─ NO → Continue                                                        │
│                                                                                  │
│  Step 2: Is path in explicit deny list?                                         │
│          └─ Check config.yaml commands.paths.deny                               │
│          └─ NO → Continue                                                        │
│                                                                                  │
│  Step 3: If allow list exists, is path in allow list?                          │
│          └─ Check config.yaml commands.paths.allow                              │
│          └─ YES → ALLOWED                                                        │
│                                                                                  │
│  Step 4: If no allow list, default to allowed                                   │
│          └─ ALLOWED                                                              │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Command Run Tracking

Every command execution is tracked as a **CommandRun**:

### CommandRun Properties

| Field | Description |
|-------|-------------|
| `command_type` | Type of command (search, explain, find, etc.) |
| `query` | The user's original query |
| `status` | pending, in_progress, completed, failed |
| `response` | AI-generated response (JSONB) |
| `context_snapshot` | Context used for the command |
| `metrics` | Duration, token usage |
| `github_comment_id` | ID of the response comment |

### State Machine

```
         ┌─────────────┐
         │   PENDING   │
         └──────┬──────┘
                │
                ▼
       ┌─────────────────┐
       │   IN_PROGRESS   │
       └────────┬────────┘
                │
       ┌────────┴────────┐
       │                 │
       ▼                 ▼
┌───────────┐     ┌───────────┐
│ COMPLETED │     │  FAILED   │
└───────────┘     └───────────┘
```

---

## Integration with Code Indexing

@sentinel commands leverage Sentinel's **Code Indexing** feature for faster, smarter searches:

### How Code Indexing Works

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        CODE INDEXING PIPELINE                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. REPOSITORY PUSH                                                             │
│  └─ GitHub webhook notifies Sentinel of new commits                            │
│                                                                                  │
│  2. CHUNKING                                                                    │
│  └─ CodeIndexingService breaks files into semantic chunks                       │
│     • Functions and methods                                                     │
│     • Classes and interfaces                                                    │
│     • Logical code blocks                                                       │
│                                                                                  │
│  3. EMBEDDING GENERATION                                                        │
│  └─ EmbeddingService creates vector embeddings for each chunk                   │
│     • Uses AI model to understand semantic meaning                              │
│     • Stores in pgvector extension                                              │
│                                                                                  │
│  4. VECTOR SEARCH                                                               │
│  └─ When @sentinel searches, it:                                                │
│     • Converts query to embedding                                               │
│     • Finds nearest neighbors in vector space                                   │
│     • Returns semantically similar code                                         │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Why This Matters

Traditional search: "find authentication" → matches files containing the word "authentication"

Semantic search: "find authentication" → also matches:
- Files implementing login logic (even if they don't use the word "authentication")
- OAuth handlers
- Session management
- Token validation
- ...and more

---

## Usage Examples

### Example 1: Understanding New Code

```
Developer: @sentinel explain how the payment processing works in this codebase

Sentinel: The payment processing in this codebase follows a service-based architecture:

**Entry Point:** `PaymentController::process()` (app/Http/Controllers/PaymentController.php:45)

**Flow:**
1. Request validation via `ProcessPaymentRequest`
2. `ProcessPayment` action orchestrates the flow
3. `PaymentGatewayService` handles provider communication
4. `PaymentRecordService` persists transaction records

**Key Components:**
- `PaymentGateway` interface (app/Contracts/PaymentGateway.php)
- `StripeGateway` and `PayPalGateway` implementations
- `PaymentEvent` for async processing

**Notable Patterns:**
- Uses Strategy pattern for multiple payment providers
- Implements idempotency keys to prevent double charges
- Failures are handled via `PaymentFailedException`

Let me know if you'd like me to dive deeper into any specific component.
```

### Example 2: Finding Related Code

```
Developer: @sentinel where is the rate limiting configured?

Sentinel: Rate limiting is configured in several places:

**Middleware Configuration:**
- `bootstrap/app.php:28` - Global rate limit middleware registered
- `app/Http/Middleware/RateLimitMiddleware.php` - Custom implementation

**Route-Level Limits:**
- `routes/api.php:15` - API routes: 60 requests/minute
- `routes/api.php:45` - Auth routes: 5 requests/minute

**Redis Configuration:**
- `config/cache.php:42` - Rate limit cache store configuration

**Custom Rate Limiters:**
```php
// app/Providers/RouteServiceProvider.php:35
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

Would you like me to explain how any of these work?
```

### Example 3: Debugging Help

```
Developer: @sentinel why might getUserProfile be returning null?

Sentinel: Looking at `getUserProfile` in `app/Services/UserService.php:89`, here are potential causes for null returns:

**1. User Not Found (Line 92)**
```php
$user = User::find($userId);
if (!$user) return null;  // ← Returns null if ID doesn't exist
```

**2. Profile Relationship Missing (Line 98)**
```php
return $user->profile;  // ← Returns null if user has no profile record
```

**3. Soft Deleted User (Line 91)**
The query doesn't include soft-deleted records. If the user was deleted:
```php
// This would work:
$user = User::withTrashed()->find($userId);
```

**Suggestions:**
- Add logging to identify which case is occurring
- Consider using `findOrFail()` if the user should always exist
- The `profile` relationship might need `withDefault()` for new users

Want me to search for where this method is called to trace the issue?
```

---

## Code Locations

| Component | Location |
|-----------|----------|
| Command Parser | `app/Services/Commands/CommandParser.php` |
| Command Agent Service | `app/Services/Commands/CommandAgentService.php` |
| Permission Service | `app/Services/Commands/CommandPermissionService.php` |
| Path Rules | `app/Services/Commands/CommandPathRules.php` |
| Command Tools | `app/Services/Commands/Tools/*.php` |
| Webhook Processing | `app/Jobs/GitHub/ProcessIssueCommentWebhook.php` |
| Execute Command Action | `app/Actions/Commands/ExecuteCommandRun.php` |
| Post Response Action | `app/Actions/Commands/PostCommandResponse.php` |
| CommandRun Model | `app/Models/CommandRun.php` |

---

## Best Practices

1. **Be Specific** - "search for user authentication" works, but "search for the middleware that validates JWT tokens" works better
2. **Provide Context** - Mention file names or class names if you know them
3. **Use Natural Language** - You don't need special syntax; just ask naturally
4. **Check Path Rules** - If you get "access denied," check your config
5. **Combine Commands** - Use explain after search to understand what you found

---

## Limitations

- Commands run in the context of the PR's base branch
- Large repositories may have search latency on first use (indexing)
- Token limits apply—very long responses may be truncated
- Rate limits: ~20 commands per hour per repository

---

*Next: [Workspace Management](./04-WORKSPACE-MANAGEMENT.md) - Multi-tenant team collaboration*
