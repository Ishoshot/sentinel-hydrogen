# Automated Code Reviews

## The Heart of Sentinel

If Sentinel were a restaurant, automated code reviews would be the kitchen—it's where the magic happens. Every time a developer opens a pull request, Sentinel springs into action, analyzing the code changes with the thoroughness of a senior engineer who's had their morning coffee and has nothing else on their calendar.

---

## How It Works

### The Review Pipeline

When a pull request arrives, it doesn't just get thrown at an AI model with a "please review this" note. Instead, it flows through a carefully orchestrated pipeline:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           THE REVIEW PIPELINE                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  STAGE 1: WEBHOOK RECEIPT                                                       │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  • Verify GitHub signature (security check)                             │    │
│  │  • Parse event type (opened, synchronize, reopened)                     │    │
│  │  • Queue for processing (no blocking the API)                           │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  STAGE 2: CONFIGURATION VALIDATION                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  ✓ Check if auto-review is enabled                                      │    │
│  │  ✓ Validate .sentinel/config.yaml (if exists)                          │    │
│  │  ✓ Evaluate trigger rules (branches, labels, authors)                   │    │
│  │  ✓ Verify BYOK API keys are available                                   │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  STAGE 3: CONTEXT BUILDING (The Secret Sauce)                                   │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Chain of 10+ collectors, each adding context:                          │    │
│  │                                                                          │    │
│  │  Priority 100: DiffCollector                                            │    │
│  │      └─ Changed files, patches, additions/deletions                     │    │
│  │                                                                          │    │
│  │  Priority  85: FileContextCollector                                     │    │
│  │      └─ Full file contents for changed files                            │    │
│  │                                                                          │    │
│  │  Priority  80: SemanticCollector                                        │    │
│  │      └─ Functions, classes, method calls, imports                       │    │
│  │                                                                          │    │
│  │  Priority  75: ImpactAnalysisCollector                                  │    │
│  │      └─ What other code might be affected?                              │    │
│  │                                                                          │    │
│  │  Priority  60: ReviewHistoryCollector                                   │    │
│  │      └─ Past reviews on this PR (for context)                           │    │
│  │                                                                          │    │
│  │  Priority  45: GuidelinesCollector                                      │    │
│  │      └─ Your team's coding standards                                    │    │
│  │                                                                          │    │
│  │  ... and more                                                            │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  STAGE 4: CONTEXT FILTERING                                                     │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Remove noise, stay within token limits:                                │    │
│  │                                                                          │    │
│  │  • VendorPathFilter - Ignore vendor/node_modules                        │    │
│  │  • BinaryFileFilter - Skip images, compiled assets                      │    │
│  │  • SensitiveDataFilter - Redact secrets (just in case)                  │    │
│  │  • RelevanceFilter - Prioritize most important context                  │    │
│  │  • TokenLimitFilter - Stay within AI model limits                       │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  STAGE 5: AI REVIEW                                                             │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  • Build structured prompt with all context                             │    │
│  │  • Send to AI provider (Claude or GPT-4)                               │    │
│  │  • Parse structured response (JSON schema enforced)                     │    │
│  │  • Extract summary, findings, and recommendations                       │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  STAGE 6: ANNOTATION POSTING                                                    │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  • Post summary comment to PR                                           │    │
│  │  • Post inline comments for specific findings                           │    │
│  │  • Record findings in database for analytics                            │    │
│  │  • Log activity for audit trail                                         │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## The Run State Machine

Every review is tracked as a "Run" with a well-defined state machine:

```
                              ┌──────────────────────┐
                              │       QUEUED         │
                              │   (Waiting in line)  │
                              └──────────┬───────────┘
                                         │
              ┌──────────────────────────┼──────────────────────────┐
              │                          │                          │
        Config Error              Job Picked Up              No Provider Keys
              │                          │                          │
              ▼                          ▼                          ▼
     ┌─────────────┐           ┌─────────────────┐        ┌─────────────┐
     │   SKIPPED   │           │   IN_PROGRESS   │        │   SKIPPED   │
     │ (with reason)│           │  (Review active)│        │ (with reason)│
     └─────────────┘           └────────┬────────┘        └─────────────┘
                                        │
                          ┌─────────────┼─────────────┐
                          │             │             │
                      Success      Exception    No Keys (runtime)
                          │             │             │
                          ▼             ▼             ▼
                   ┌───────────┐ ┌───────────┐ ┌───────────┐
                   │ COMPLETED │ │  FAILED   │ │  SKIPPED  │
                   │           │ │           │ │           │
                   └───────────┘ └───────────┘ └───────────┘
```

### State Definitions

| State | What It Means | What Happens Next |
|-------|---------------|-------------------|
| **Queued** | Run created, waiting for worker | Worker picks it up for processing |
| **In Progress** | Review actively executing | AI analysis and findings generation |
| **Completed** | Review finished successfully | Annotations posted to GitHub |
| **Failed** | Something went wrong | Error recorded, notification posted |
| **Skipped** | Review intentionally skipped | Reason recorded, may post comment |

---

## What Gets Reviewed?

Sentinel analyzes code across several **categories**, each focusing on a specific aspect of code quality:

### Review Categories

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           REVIEW CATEGORIES                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  🔒 SECURITY (Default: ON)                                                      │
│  ├─ SQL injection vulnerabilities                                               │
│  ├─ Cross-site scripting (XSS)                                                  │
│  ├─ Authentication/authorization flaws                                          │
│  ├─ Sensitive data exposure                                                     │
│  └─ Insecure dependencies                                                       │
│                                                                                  │
│  ✅ CORRECTNESS (Default: ON)                                                   │
│  ├─ Logic errors and bugs                                                       │
│  ├─ Edge case handling                                                          │
│  ├─ Type mismatches                                                             │
│  ├─ Null/undefined handling                                                     │
│  └─ Race conditions                                                             │
│                                                                                  │
│  ⚡ PERFORMANCE (Default: ON)                                                   │
│  ├─ N+1 query problems                                                          │
│  ├─ Inefficient algorithms                                                      │
│  ├─ Memory leaks                                                                │
│  ├─ Unnecessary computations                                                    │
│  └─ Resource exhaustion risks                                                   │
│                                                                                  │
│  🧹 MAINTAINABILITY (Default: ON)                                               │
│  ├─ Code complexity                                                             │
│  ├─ Duplication                                                                 │
│  ├─ Poor naming                                                                 │
│  ├─ Missing abstraction                                                         │
│  └─ Technical debt                                                              │
│                                                                                  │
│  🎨 STYLE (Default: OFF)                                                        │
│  └─ Often handled by linters, so disabled by default                            │
│                                                                                  │
│  🧪 TESTING (Default: OFF)                                                      │
│  └─ Test coverage and quality                                                   │
│                                                                                  │
│  📚 DOCUMENTATION (Default: OFF)                                                │
│  └─ Missing or outdated documentation                                           │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Severity Levels

Every finding is assigned a severity level:

| Level | Icon | Meaning | Action Required |
|-------|------|---------|-----------------|
| **Critical** | 🔴 | Security vulnerabilities, data loss risks | Immediate fix before merge |
| **High** | 🟠 | Significant bugs, security concerns | Should be addressed |
| **Medium** | 🟡 | Quality issues, potential bugs | Recommended to fix |
| **Low** | 🟢 | Minor improvements, best practices | Nice to have |
| **Info** | 🔵 | Suggestions, observations | For consideration |

---

## The Review Output

When a review completes, it produces a structured result:

### Summary Section

```markdown
## 🔍 Review Summary

**Verdict:** ✅ Approve / ⚠️ Request Changes / 💬 Comment Only

**Risk Level:** Low / Medium / High / Critical

### Overview
A clear, concise summary of what this PR does and the overall code quality.

### Strengths
- Well-structured error handling
- Good test coverage
- Clear naming conventions

### Concerns
- Missing input validation on user-facing endpoint
- Potential N+1 query in the user listing

### Recommendations
- Add rate limiting to the new API endpoint
- Consider caching the frequently accessed data
```

### Findings Section

Each finding includes:

```markdown
### 🔴 SQL Injection Risk

**File:** `app/Http/Controllers/UserController.php`
**Lines:** 45-47
**Category:** Security
**Confidence:** High

#### Description
Raw user input is being interpolated directly into a SQL query,
creating a SQL injection vulnerability.

#### Current Code
```php
$users = DB::select("SELECT * FROM users WHERE name = '$name'");
```

#### Suggested Fix
```php
$users = User::where('name', $name)->get();
```

#### Why This Matters
SQL injection is one of the most common and dangerous vulnerabilities.
An attacker could:
- Access sensitive data from other users
- Modify or delete data
- In some cases, execute system commands

#### References
- [OWASP SQL Injection](https://owasp.org/www-community/attacks/SQL_Injection)
- CWE-89
```

---

## The Context Engine

The **Context Engine** is one of Sentinel's most sophisticated components. It's responsible for gathering all the information the AI needs to provide an accurate review.

### Why Context Matters

Imagine reviewing a PR that changes a function called `validateInput()`. Without context, you might miss that:
- This function is called from 15 different places
- It was recently modified to fix a security issue
- Your team has a guideline about validation patterns
- There's a similar function in another file that should stay consistent

The Context Engine gathers all this information systematically.

### Context Collectors

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          CONTEXT COLLECTORS                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Collector                  Priority    What It Collects                        │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  DiffCollector               100        Changed files, patches, stats           │
│  FileContextCollector         85        Full file contents for context          │
│  SemanticCollector            80        Code structure (functions, classes)     │
│  LinkedIssueCollector         80        Related GitHub issues                   │
│  ImpactAnalysisCollector      75        Code that might be affected             │
│  PRCommentCollector           70        Existing PR discussion                  │
│  ReviewHistoryCollector       60        Past reviews on this repository         │
│  ProjectContextCollector      55        Project structure and patterns          │
│  RepositoryContextCollector   50        README, CONTRIBUTING, etc.              │
│  GuidelinesCollector          45        Team coding guidelines                  │
│                                                                                  │
│  Higher priority = runs first and gets more token budget                        │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Context Filters

After collection, filters clean up the context:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          CONTEXT FILTERS                                         │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Filter                     Order    What It Does                               │
│  ─────────────────────────────────────────────────────────────────────────────  │
│  VendorPathFilter            10       Remove vendor/node_modules paths          │
│  ConfiguredPathFilter        15       Apply user's ignore patterns              │
│  BinaryFileFilter            20       Skip images, compiled files               │
│  SensitiveDataFilter         30       Redact potential secrets                  │
│  RelevanceFilter             40       Prioritize most relevant files            │
│  TokenLimitFilter           100       Fit within AI model's context window      │
│                                                                                  │
│  Lower order = runs first                                                        │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## AI Provider Integration

Sentinel uses the **BYOK (Bring Your Own Key)** model for AI providers. This means:

### How It Works

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           BYOK AI INTEGRATION                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. You configure your API key in Sentinel dashboard                            │
│     (key is encrypted at rest, never stored in plain text)                      │
│                                                                                  │
│  2. When a review runs, Sentinel:                                               │
│     ┌────────────────────────────────────────────────────────────────────┐     │
│     │  Provider Selection                                                 │     │
│     │  ├─ Check if preferred provider set in config                       │     │
│     │  ├─ Verify API key exists for provider                              │     │
│     │  └─ If fallback enabled, try alternative providers on failure       │     │
│     └────────────────────────────────────────────────────────────────────┘     │
│                                                                                  │
│  3. The AI call is made using YOUR API key                                      │
│     • You pay the AI provider directly                                          │
│     • No Sentinel markup on AI costs                                            │
│     • Full transparency on token usage                                          │
│                                                                                  │
│  4. Usage is tracked for your reference                                         │
│     • Input tokens used                                                         │
│     • Output tokens generated                                                   │
│     • Visible in Analytics dashboard                                            │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Supported Providers

| Provider | Models | Best For |
|----------|--------|----------|
| **Anthropic** | Claude 3.5 Sonnet, Claude 3 Opus | Code understanding, nuanced reviews |
| **OpenAI** | GPT-4o, GPT-4 Turbo | General purpose, fast reviews |

### Fallback Behavior

When `provider.fallback: true` (default):

1. Try preferred provider (if set)
2. If that fails (no key, rate limit, error), try next provider
3. Up to 3 fallback attempts
4. If all fail, the Run is marked as Failed

When `provider.fallback: false`:
- Only try the preferred provider
- Fail immediately if unavailable

---

## Manual Review Triggers

Not everything needs to be automatic. You can trigger reviews manually:

### Comment Trigger

Post a comment on any PR:

```
/review
```

This triggers a full review, even if auto-review is disabled.

### API Trigger

```bash
POST /api/v1/workspaces/{workspace}/repositories/{repository}/runs
{
  "pr_number": 123
}
```

---

## Code Locations

For developers who want to understand the implementation:

| Component | Location |
|-----------|----------|
| Webhook Handler | `app/Http/Controllers/GitHub/WebhookController.php` |
| Webhook Processing Job | `app/Jobs/GitHub/ProcessPullRequestWebhook.php` |
| Review Execution Action | `app/Actions/Reviews/ExecuteReviewRun.php` |
| Context Engine | `app/Services/Context/ContextEngine.php` |
| Review Engine | `app/Services/Reviews/PrismReviewEngine.php` |
| Annotation Posting | `app/Actions/Reviews/PostRunAnnotations.php` |
| Run Model | `app/Models/Run.php` |
| Finding Model | `app/Models/Finding.php` |

---

## Common Questions

### Why was my PR skipped?

Check the skip reason:
- **no_provider_keys**: No API key configured. Add one in Repository Settings.
- **config_error**: Invalid `.sentinel/config.yaml`. Check syntax.
- **trigger_rule_***: PR didn't match trigger rules (branch, labels, etc.)

### Why are some files not reviewed?

Files might be excluded by:
- Default ignores (vendor, node_modules, lock files)
- Your `paths.ignore` configuration
- Binary file detection
- Token budget limits (less important files deprioritized)

### Can I review the same PR again?

Yes! Push new commits (triggers automatic re-review) or use `/review` comment.

### How long does a review take?

Typical times:
- Small PR (< 10 files): 15-30 seconds
- Medium PR (10-50 files): 30-90 seconds
- Large PR (50+ files): 1-3 minutes

Complex context gathering and large diffs take longer.

---

## Best Practices

1. **Keep PRs focused** - Smaller PRs get better reviews
2. **Write descriptive PR titles/descriptions** - Helps the AI understand intent
3. **Configure guidelines** - Team standards improve review relevance
4. **Set appropriate severity thresholds** - Reduce noise for high-volume repos
5. **Use labels wisely** - Skip reviews for trivial changes with `skip-review` label

---

*Next: [Briefings](./02-BRIEFINGS.md) - AI-generated narrative reports*
