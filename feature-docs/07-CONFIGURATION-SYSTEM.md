# Configuration System

## Making Sentinel Work Your Way

Every team is different. What matters to a fintech startup isn't the same as what matters to a social media company. The Configuration System lets you customize Sentinel's behavior to match your team's needs, standards, and workflows.

Configuration in Sentinel follows a simple principle: **sensible defaults, easy overrides**.

---

## Configuration Hierarchy

Sentinel uses a layered configuration system with clear precedence:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     CONFIGURATION PRECEDENCE                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Priority 1 (Highest)                                                           │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  REPOSITORY CONFIG FILE                                                 │    │
│  │  .sentinel/config.yaml                                                 │    │
│  │                                                                          │    │
│  │  • Lives in your repository                                             │    │
│  │  • Version controlled with your code                                   │    │
│  │  • Most specific configuration                                         │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                              │                                                   │
│                              ▼ Falls back to                                    │
│  Priority 2                                                                     │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  REPOSITORY DASHBOARD SETTINGS                                          │    │
│  │  Configured via Sentinel UI                                             │    │
│  │                                                                          │    │
│  │  • Web-based configuration                                              │    │
│  │  • Good for repos without config files                                 │    │
│  │  • Quick changes without commits                                       │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                              │                                                   │
│                              ▼ Falls back to                                    │
│  Priority 3                                                                     │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  WORKSPACE DEFAULTS                                                     │    │
│  │  Settings that apply to all repositories in workspace                  │    │
│  │                                                                          │    │
│  │  • Set once, apply everywhere                                          │    │
│  │  • Good for organization-wide standards                                │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                              │                                                   │
│                              ▼ Falls back to                                    │
│  Priority 4 (Lowest)                                                            │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  SYSTEM DEFAULTS                                                        │    │
│  │  Built into Sentinel                                                    │    │
│  │                                                                          │    │
│  │  • Security: ON                                                         │    │
│  │  • Correctness: ON                                                      │    │
│  │  • Performance: ON                                                      │    │
│  │  • Maintainability: ON                                                  │    │
│  │  • Style: OFF                                                           │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## The Config File

### Location and Format

Create a file at `.sentinel/config.yaml` in your repository's default branch:

```yaml
version: 1

triggers:
  target_branches:
    - main
    - develop
  skip_labels:
    - wip
    - skip-review

paths:
  ignore:
    - "*.lock"
    - "vendor/**"
  sensitive:
    - "**/auth/**"

review:
  tone: constructive
  categories:
    security: true
    correctness: true
    performance: true
    maintainability: true
    style: false

annotations:
  style: review
  post_threshold: medium
```

### Full Configuration Reference

```yaml
# Schema version (required)
version: 1

# When to trigger reviews
triggers:
  # Only review PRs targeting these branches
  target_branches:
    - main
    - master
    - "release/*"  # Glob patterns supported

  # Skip PRs from these source branches
  skip_source_branches:
    - "dependabot/*"

  # Skip PRs with these labels
  skip_labels:
    - wip
    - skip-review
    - experimental

  # Skip PRs from these authors
  skip_authors:
    - "dependabot[bot]"
    - "renovate[bot]"

# File path rules
paths:
  # Files to exclude from review
  ignore:
    - "*.lock"
    - "*.min.js"
    - "vendor/**"
    - "node_modules/**"
    - "dist/**"

  # If set, only review files matching these patterns
  include:
    - "app/**"
    - "src/**"

  # Files requiring extra security scrutiny
  sensitive:
    - "**/auth/**"
    - "**/security/**"
    - "**/*password*"
    - "**/*secret*"

# Review behavior
review:
  # Minimum severity to report
  min_severity: low  # info, low, medium, high, critical

  # Maximum findings per review
  max_findings: 25

  # Review categories
  categories:
    security: true
    correctness: true
    performance: true
    maintainability: true
    style: false
    testing: false
    documentation: false

  # Feedback tone
  tone: constructive  # constructive, direct, educational, minimal

  # Response language (ISO 639-1)
  language: en

  # Custom focus areas
  focus:
    - "SQL injection prevention"
    - "Laravel best practices"

# Team guidelines to include in context
guidelines:
  - path: docs/CODING_STANDARDS.md
    description: Team coding conventions
  - path: docs/ARCHITECTURE.md
    description: System architecture decisions

# How to post findings
annotations:
  # Posting style: review, comment, check
  style: review

  # Minimum severity to post inline
  post_threshold: medium

  # Group findings or individual comments
  grouped: true

  # Include code suggestions
  include_suggestions: true

# AI provider preferences
provider:
  # Preferred provider: anthropic, openai
  preferred: anthropic

  # Specific model (optional)
  model: claude-sonnet-4-5-20250929

  # Try other providers on failure
  fallback: true
```

---

## Configuration Sections Explained

### Triggers

Control when reviews happen:

```yaml
triggers:
  # Only these branches trigger reviews
  target_branches:
    - main
    - develop
    - "release/*"    # Matches release/1.0, release/2.0
    - "feature/**"   # Matches feature/foo/bar
```

**Skip Conditions:**

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     TRIGGER EVALUATION                                           │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  PR: feature/new-auth → main                                                    │
│  Author: alice                                                                  │
│  Labels: [urgent]                                                               │
│                                                                                  │
│  Check 1: Is target branch in target_branches?                                  │
│           main ∈ [main, develop] → ✅ PASS                                      │
│                                                                                  │
│  Check 2: Is source branch in skip_source_branches?                             │
│           feature/new-auth ∉ [dependabot/*] → ✅ PASS                           │
│                                                                                  │
│  Check 3: Does PR have any skip_labels?                                         │
│           [urgent] ∩ [wip, skip-review] = ∅ → ✅ PASS                           │
│                                                                                  │
│  Check 4: Is author in skip_authors?                                            │
│           alice ∉ [dependabot[bot]] → ✅ PASS                                   │
│                                                                                  │
│  Result: All checks passed → TRIGGER REVIEW                                     │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Paths

Control which files are reviewed:

```yaml
paths:
  # Always ignore these
  ignore:
    - "*.lock"           # Lock files change frequently
    - "vendor/**"        # Third-party code
    - "node_modules/**"  # Dependencies
    - "dist/**"          # Build output
    - "*.min.js"         # Minified files

  # Allowlist mode (if set)
  include:
    - "app/**"
    - "src/**"
    - "lib/**"

  # Extra scrutiny for these
  sensitive:
    - "**/auth/**"
    - "**/authentication/**"
    - "**/authorization/**"
    - "**/*password*"
    - "**/*secret*"
    - "**/*token*"
```

**Path Pattern Matching:**

| Pattern | Matches | Doesn't Match |
|---------|---------|---------------|
| `*.lock` | `composer.lock`, `package-lock.json` | `locks/file.txt` |
| `vendor/**` | `vendor/laravel/framework/composer.json` | `vendors.txt` |
| `**/auth/**` | `app/auth/login.php`, `src/lib/auth/token.js` | `auth.php` |
| `**/*password*` | `reset-password.php`, `app/password_helper.js` | `passwd` |

### Review

Configure review behavior:

```yaml
review:
  min_severity: low
  max_findings: 25
  categories:
    security: true
    correctness: true
    performance: true
    maintainability: true
    style: false
  tone: constructive
  focus:
    - "OWASP Top 10"
    - "N+1 query prevention"
```

**Severity Levels:**

| Level | When to Use |
|-------|-------------|
| `info` | See everything, including suggestions |
| `low` | Default; minor issues and best practices |
| `medium` | Only potential bugs and quality issues |
| `high` | Only significant bugs and security concerns |
| `critical` | Only security vulnerabilities |

**Tone Options:**

| Tone | Style | Best For |
|------|-------|----------|
| `constructive` | Balanced, explains why | Most teams |
| `direct` | Concise, to the point | Experienced teams |
| `educational` | Detailed explanations | Learning environments |
| `minimal` | Brief, essential only | High-volume repos |

### Guidelines

Include team documentation in review context:

```yaml
guidelines:
  - path: docs/CODING_STANDARDS.md
    description: Team coding conventions
  - path: docs/ARCHITECTURE.md
    description: System architecture and patterns
  - path: docs/SECURITY.md
    description: Security guidelines and requirements
```

**Limits:**
- Maximum 10 guideline files
- Maximum 50KB per file

**How Guidelines Help:**

```
Without Guidelines:
  AI sees: "User::where('email', $email)->first()"
  AI thinks: "Standard Eloquent query, looks fine"

With Guidelines (CODING_STANDARDS.md says "Always use firstOrFail for user lookups"):
  AI sees: "User::where('email', $email)->first()"
  AI suggests: "Consider using firstOrFail() per team guidelines"
```

### Annotations

Control how findings appear on PRs:

```yaml
annotations:
  style: review        # review, comment, check
  post_threshold: medium
  grouped: true
  include_suggestions: true
```

**Annotation Styles:**

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     ANNOTATION STYLES                                            │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  STYLE: review                                                                  │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Posted as: GitHub Pull Request Review                                  │    │
│  │  Features:  ✓ Inline comments on specific lines                        │    │
│  │             ✓ Summary comment                                           │    │
│  │             ✓ Can request changes/approve                              │    │
│  │  Best for:  Most use cases                                             │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│  STYLE: comment                                                                 │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Posted as: Individual issue comments                                   │    │
│  │  Features:  ✓ Simple comments                                          │    │
│  │             ✗ No inline positioning                                    │    │
│  │             ✗ No review status                                         │    │
│  │  Best for:  Simple feedback, legacy systems                            │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│  STYLE: check                                                                   │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Posted as: GitHub Check Run with annotations                          │    │
│  │  Features:  ✓ Appears in Checks tab                                    │    │
│  │             ✓ Can block merges                                         │    │
│  │             ✓ Integrates with CI                                       │    │
│  │  Best for:  CI/CD integration                                          │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Provider

AI provider preferences:

```yaml
provider:
  preferred: anthropic
  model: claude-sonnet-4-5-20250929
  fallback: true
```

**Note:** API keys are NOT configured here. They're set in the Sentinel dashboard for security.

---

## Config Validation

Sentinel validates your config file and shows errors clearly:

### Common Errors

| Error | Cause | Fix |
|-------|-------|-----|
| `Invalid version` | Wrong or missing version | Add `version: 1` |
| `Unknown property` | Typo in key name | Check spelling |
| `Invalid enum value` | Wrong value for field | Check allowed values |
| `Guideline not found` | File path doesn't exist | Verify file path |
| `YAML syntax error` | Invalid YAML | Check indentation |

### Error Display

When config has errors:
1. Reviews are **skipped** for the repository
2. Error message posted as PR comment
3. Error visible in Sentinel dashboard
4. Activity logged for debugging

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│  ⚠️ Sentinel Configuration Error                                                │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  Reviews are disabled due to a configuration error in `.sentinel/config.yaml`  │
│                                                                                  │
│  Error: Invalid value for 'review.min_severity'. Expected one of:              │
│         info, low, medium, high, critical                                       │
│         Got: 'warning'                                                          │
│                                                                                  │
│  Please fix this error and push to the default branch.                         │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Config Sync

Sentinel automatically syncs your config when:

1. **Repository first connected** - Initial config fetch
2. **Push to default branch** - If `.sentinel/` files changed
3. **Manual sync** - Via dashboard button

### Sync Flow

```
Push to main branch
        │
        ▼
GitHub sends push webhook
        │
        ▼
Check if .sentinel/ files changed
        │
   ┌────┴────┐
   │         │
  Yes       No
   │         │
   ▼         ▼
Fetch new   Do nothing
config from
GitHub
   │
   ▼
Parse YAML
   │
   ├── Valid → Update settings
   │
   └── Invalid → Mark as error
                 Skip reviews
```

---

## Dashboard vs. Config File

### When to Use Config File

✅ Version control configuration with code
✅ Review changes through PRs
✅ Team-wide visibility into settings
✅ Rollback configuration easily
✅ Different config per branch (future)

### When to Use Dashboard

✅ Quick experiments
✅ Repos you don't control
✅ Simple setups without custom needs
✅ Temporary overrides

### Precedence Example

```
Scenario: Config file has tone: direct
          Dashboard has tone: constructive

Result: tone: direct (config file wins)
```

---

## Code Locations

| Component | Location |
|-----------|----------|
| Config Parser Service | `app/Services/SentinelConfig/SentinelConfigParserService.php` |
| Config Schema | `app/Services/SentinelConfig/SentinelConfigSchema.php` |
| Config DTOs | `app/DataTransferObjects/SentinelConfig/*.php` |
| Sync Action | `app/Actions/SentinelConfig/SyncRepositorySentinelConfig.php` |
| Fetch Action | `app/Actions/SentinelConfig/FetchSentinelConfig.php` |

---

## Best Practices

1. **Start minimal** - Begin with defaults, add customization as needed
2. **Use version control** - Config file > dashboard for important settings
3. **Document guidelines** - Include why rules exist, not just what they are
4. **Test changes** - Use a non-critical repo to test config changes
5. **Review config changes** - Treat config like code; review before merging

---

*Next: [Billing & Plans](./08-BILLING-AND-PLANS.md) - Subscription management*
