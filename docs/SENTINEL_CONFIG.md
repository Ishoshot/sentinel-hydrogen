# Sentinel Configuration Guide

This guide explains how to configure Sentinel's behavior for your repository using the `.sentinel/config.yaml` configuration file.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Configuration Reference](#configuration-reference)
  - [version](#version)
  - [triggers](#triggers)
  - [paths](#paths)
  - [review](#review)
  - [guidelines](#guidelines)
  - [annotations](#annotations)
- [Examples](#examples)
- [Troubleshooting](#troubleshooting)

---

## Overview

Sentinel allows you to customize review behavior per-repository by placing a configuration file at `.sentinel/config.yaml` in your repository's default branch.

### How It Works

1. Create a `.sentinel/config.yaml` file in your repository
2. Commit and push to your default branch (e.g., `main`)
3. Sentinel automatically detects and parses the configuration
4. All future reviews use your custom settings

### Configuration Sync

Sentinel syncs your configuration:
- When you first connect the repository
- When you push changes to the default branch that modify `.sentinel/` files
- When you manually sync repositories from the dashboard

### Error Handling

If your configuration file has errors:
- Sentinel will **skip reviews** until the error is fixed
- A comment is posted to any opened PR explaining the error
- The error is visible in your repository settings on the Sentinel dashboard
- Previous valid configuration is not retained (reviews are skipped)

---

## Quick Start

### Minimal Configuration

Create `.sentinel/config.yaml`:

```yaml
version: 1
```

This uses all defaults. Sentinel will review PRs targeting `main` or `master`.

### Common Configuration

```yaml
version: 1

triggers:
  target_branches:
    - main
    - develop
  skip_labels:
    - wip
    - skip-review

review:
  tone: constructive
  categories:
    security: true
    correctness: true
    performance: true
    maintainability: true
    style: false
```

### Full Example

See `.sentinel/config.example.yaml` for a complete example with all options documented.

---

## Configuration Reference

### version

**Required.** The schema version number.

```yaml
version: 1
```

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `version` | integer | Yes | Schema version. Currently only `1` is supported. |

---

### triggers

Control when Sentinel reviews pull requests.

```yaml
triggers:
  target_branches:
    - main
    - develop
    - "release/*"
  skip_source_branches:
    - "dependabot/*"
  skip_labels:
    - wip
    - skip-review
  skip_authors:
    - "dependabot[bot]"
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `target_branches` | array of strings | `[main, master]` | Only review PRs targeting these branches. Supports glob patterns. |
| `skip_source_branches` | array of strings | `[]` | Skip PRs from these source branches. Supports glob patterns. |
| `skip_labels` | array of strings | `[]` | Skip PRs with any of these labels. |
| `skip_authors` | array of strings | `[]` | Skip PRs from these authors (useful for bots). |

#### Glob Pattern Support

Branch patterns support these wildcards:
- `*` - Matches any characters except `/`
- `**` - Matches any characters including `/`
- `?` - Matches a single character

**Examples:**
- `release/*` - Matches `release/1.0`, `release/2.0`, but not `release/1.0/hotfix`
- `feature/**` - Matches `feature/foo`, `feature/foo/bar`
- `hotfix-?` - Matches `hotfix-1`, `hotfix-a`

#### Skip Behavior

When a PR is skipped:
- A Run is created with status `skipped`
- No PR comment is posted (silent skip)
- The skip reason is recorded in the Run metadata

---

### paths

Control which files are included in reviews.

```yaml
paths:
  ignore:
    - "*.lock"
    - "vendor/**"
    - "node_modules/**"
  include:
    - "app/**"
    - "src/**"
  sensitive:
    - "**/auth/**"
    - "**/*password*"
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `ignore` | array of strings | `[*.lock, vendor/**, node_modules/**]` | Files matching these patterns are excluded from review. |
| `include` | array of strings | `[]` | If set, only files matching these patterns are reviewed (allowlist mode). |
| `sensitive` | array of strings | `[]` | Files requiring extra security scrutiny. |

#### Pattern Matching

Supports glob patterns:
- `*` - Matches any filename characters
- `**` - Matches any path, including subdirectories
- `?` - Matches a single character

**Examples:**
- `*.lock` - Matches `composer.lock`, `package-lock.json`
- `vendor/**` - Matches all files under `vendor/`
- `**/auth/**` - Matches `app/auth/login.php`, `src/lib/auth/token.js`
- `**/*password*` - Matches any file with "password" in the name

#### Include vs Ignore

- **Ignore only**: All files are reviewed except those matching `ignore` patterns
- **Include + Ignore**: Only files matching `include` patterns are reviewed, then `ignore` is applied

**Warning:** Setting `include` activates allowlist mode. Files not matching `include` patterns will not be reviewed!

#### Sensitive Files

Files matching `sensitive` patterns receive additional scrutiny:
- Security-focused analysis is prioritized
- Authentication/authorization patterns are highlighted
- Data handling is examined more carefully

---

### review

Configure review behavior, thresholds, and feedback style.

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
  language: en
  focus:
    - "SQL injection prevention"
    - "Laravel best practices"
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `min_severity` | string | `low` | Minimum severity to report. Options: `info`, `low`, `medium`, `high`, `critical` |
| `max_findings` | integer | `25` | Maximum findings per review. `0` = unlimited. |
| `categories` | object | See below | Enable/disable review categories. |
| `tone` | string | `constructive` | Feedback tone. Options: `constructive`, `direct`, `educational`, `minimal` |
| `language` | string | `en` | Response language (ISO 639-1 code). |
| `focus` | array of strings | `[]` | Custom focus areas for this codebase. |

#### Severity Levels

| Level | Description | Examples |
|-------|-------------|----------|
| `critical` | Security vulnerabilities, data loss risks | SQL injection, auth bypass |
| `high` | Significant bugs, security concerns | Logic errors, XSS |
| `medium` | Quality issues, potential bugs | Error handling, edge cases |
| `low` | Minor improvements, best practices | Code organization, naming |
| `info` | Suggestions, observations | Style preferences, alternatives |

#### Categories

| Category | Default | Description |
|----------|---------|-------------|
| `security` | `true` | SQL injection, XSS, authentication, authorization |
| `correctness` | `true` | Logic errors, incorrect behavior, edge cases |
| `performance` | `true` | N+1 queries, inefficient algorithms, resource usage |
| `maintainability` | `true` | Code organization, complexity, readability |
| `style` | `false` | Code style, formatting (often handled by linters) |

#### Tone Options

| Tone | Description | Best For |
|------|-------------|----------|
| `constructive` | Balanced feedback with explanations and suggestions | Most teams |
| `direct` | Concise, to-the-point feedback | Experienced teams |
| `educational` | Detailed explanations, teaching moments | Learning/junior teams |
| `minimal` | Brief, essential feedback only | High-volume repos |

#### Focus Areas

Custom focus areas help Sentinel prioritize specific concerns for your codebase:

```yaml
focus:
  - "SQL injection prevention"
  - "Laravel best practices"
  - "React hooks rules"
  - "Memory management"
```

These are natural language descriptions of what matters most to your team.

---

### guidelines

Include custom team documentation in review context.

```yaml
guidelines:
  - path: docs/CODING_STANDARDS.md
    description: "Team coding conventions"
  - path: docs/ARCHITECTURE.md
    description: "System architecture decisions"
```

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `path` | string | Yes | Path to the guideline file in your repository. |
| `description` | string | No | Brief description of what this guideline covers. |

#### Supported Formats

- `.md` - Markdown
- `.mdx` - MDX (Markdown with JSX)
- `.blade.php` - Laravel Blade templates

#### Limits

- Maximum **5 guideline files** per repository
- Maximum **50KB per file** (content is truncated if larger)

#### How Guidelines Are Used

Sentinel reads your guideline files and includes them in the review context. The AI considers these documents when:
- Evaluating code against team standards
- Suggesting improvements
- Identifying violations of team conventions

**Tip:** Include your most important coding standards and architectural decisions.

---

### annotations

Configure how findings are posted to pull requests.

```yaml
annotations:
  style: review
  post_threshold: medium
  grouped: true
  include_suggestions: true
```

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `style` | string | `review` | How to post annotations: `review`, `comment`, `check` |
| `post_threshold` | string | `medium` | Minimum severity to post as annotation. |
| `grouped` | boolean | `true` | Group findings into single review vs individual comments. |
| `include_suggestions` | boolean | `true` | Include code suggestions when possible. |

#### Annotation Styles

| Style | Description | GitHub Feature |
|-------|-------------|----------------|
| `review` | PR review with inline comments | Pull Request Review |
| `comment` | Individual PR comments | Issue Comments |
| `check` | Check Run with annotations | GitHub Checks |

**Recommendation:** Use `review` for the best experience with inline comments.

#### Post Threshold

Findings below `post_threshold` are included in the review summary but not posted as inline annotations. This helps reduce noise while keeping important findings visible.

---

## Examples

### Laravel Application

```yaml
version: 1

triggers:
  target_branches:
    - main
    - develop
  skip_authors:
    - "dependabot[bot]"

paths:
  ignore:
    - "*.lock"
    - "vendor/**"
    - "node_modules/**"
    - "public/build/**"
    - "storage/**"
    - "bootstrap/cache/**"
  sensitive:
    - "app/Http/Middleware/**"
    - "app/Policies/**"
    - "**/auth/**"

review:
  tone: constructive
  focus:
    - "Laravel best practices"
    - "Eloquent N+1 query prevention"
    - "SQL injection prevention"
    - "CSRF protection"

guidelines:
  - path: docs/CODING_STANDARDS.md
    description: "Team coding conventions"
```

### React/TypeScript Application

```yaml
version: 1

triggers:
  target_branches:
    - main
  skip_labels:
    - wip
    - experimental

paths:
  ignore:
    - "*.lock"
    - "node_modules/**"
    - "dist/**"
    - "build/**"
    - "coverage/**"
  include:
    - "src/**"
    - "lib/**"

review:
  tone: educational
  categories:
    security: true
    correctness: true
    performance: true
    maintainability: true
    style: false  # Using ESLint/Prettier
  focus:
    - "React hooks rules"
    - "TypeScript type safety"
    - "Memory leaks in useEffect"

annotations:
  post_threshold: low
```

### High-Volume Repository

```yaml
version: 1

triggers:
  skip_labels:
    - trivial
    - docs-only
    - skip-review
  skip_authors:
    - "dependabot[bot]"
    - "renovate[bot]"

review:
  min_severity: medium
  max_findings: 10
  tone: minimal
  categories:
    security: true
    correctness: true
    performance: false
    maintainability: false
    style: false

annotations:
  post_threshold: high
  grouped: true
```

### Security-Focused Configuration

```yaml
version: 1

paths:
  sensitive:
    - "**/auth/**"
    - "**/authentication/**"
    - "**/authorization/**"
    - "**/crypto/**"
    - "**/security/**"
    - "**/*password*"
    - "**/*secret*"
    - "**/*token*"
    - "**/*key*"
    - "**/*credential*"

review:
  min_severity: info
  max_findings: 50
  categories:
    security: true
    correctness: true
    performance: false
    maintainability: false
    style: false
  focus:
    - "OWASP Top 10 vulnerabilities"
    - "Authentication bypass"
    - "Authorization flaws"
    - "Injection attacks"
    - "Sensitive data exposure"

annotations:
  post_threshold: low
```

---

## Troubleshooting

### Configuration Not Applied

1. **Check file location**: Must be exactly `.sentinel/config.yaml`
2. **Check branch**: Config is read from the default branch (usually `main`)
3. **Push to default branch**: Changes must be pushed, not just committed
4. **Wait for sync**: Config is synced on push; may take a few seconds

### YAML Syntax Errors

Common YAML mistakes:
- Incorrect indentation (use 2 spaces, no tabs)
- Missing colons after keys
- Unquoted special characters

Use a YAML validator to check syntax before committing.

### Configuration Errors

Check the Sentinel dashboard for error messages:
1. Go to your repository settings
2. Look for "Sentinel Configuration" section
3. Error message shows what's wrong

Common errors:
- Invalid `version` number
- Unknown property names
- Invalid enum values (e.g., `min_severity: invalid`)
- Guidelines file not found
- Guidelines file too large

### Reviews Not Running

If reviews aren't happening:
1. Check if auto-review is enabled for the repository
2. Check if PR matches `target_branches`
3. Check if PR is being skipped (labels, authors, source branch)
4. Check for configuration errors in dashboard

### Findings Not Appearing

If reviews run but findings are missing:
1. Check `min_severity` threshold
2. Check `categories` - disabled categories won't generate findings
3. Check `paths.ignore` - files may be excluded
4. Check `paths.include` - if set, only matching files are reviewed

---

## Schema Reference

For the complete JSON Schema used for validation, see the source code at:
`app/Services/SentinelConfig/SentinelConfigSchema.php`

---

## Getting Help

- **Documentation**: This file and inline comments in `.sentinel/config.example.yaml`
- **Dashboard**: View configuration status and errors in repository settings
- **Support**: Contact your Sentinel administrator

---

*Last updated: 2026-01-11*
