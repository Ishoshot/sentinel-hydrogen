---
name: commit-breakdown
autoApply: false
description: >-
  Break down staged/unstaged changes into logical, well-organized commits (max 5 files per commit).
  Activates when the user invokes /commit-breakdown.
---

# Commit Breakdown Workflow

This workflow organizes your uncommitted changes into logical, atomic commits with proper conventional commit messages.

## Instructions for AI

When the user triggers `/commit-breakdown`, follow these steps:

### Step 1: Analyze Uncommitted Files

Run `git status -s` to get all modified (M), added (A), deleted (D), and untracked (??) files.

### Step 2: Categorize Files Logically

Group files into logical commits based on these priorities (in order):

1. **Database Layer**: Migrations, seeders, factories
2. **Models**: Eloquent models, casts, scopes
3. **Domain Layer**: Value objects, DTOs, domain services, domain events, exceptions
4. **Application Layer**: Commands, handlers, queries, query handlers
5. **Infrastructure Layer**: Repository implementations, adapters, external service integrations
6. **HTTP Layer - Validation**: Form requests, validation rules
7. **HTTP Layer - Controllers**: Controllers, middleware
8. **HTTP Layer - Resources**: API resources, transformers
9. **Service Providers**: Provider registration files
10. **Services & Actions**: Service classes, action classes
11. **Configuration**: Config files, environment-related changes
12. **Routes**: Route definitions
13. **Tests**: Unit tests, feature tests, integration tests
14. **Frontend**: Views, JS, CSS, assets
15. **Documentation**: README, docs, comments

### Step 3: Apply Commit Constraints

- **Maximum 5 files per commit** (hard limit)
- **Minimum 1 file per commit**
- If a logical group has more than 5 files, split into multiple commits with descriptive suffixes (e.g., "add admin CRUD commands (part 1)")
- Keep related files together when possible (e.g., Command + Handler pairs)
- Migrations should be in their own commits or with directly related model

### Step 4: Present the Commit Plan

Before executing, show a markdown table with the planned commits:

```
| # | Description | Files |
|---|-------------|-------|
| 1 | feat(scope): description of changes | 3 |
| 2 | feat(scope): description of changes | 4 |
...
```

### Step 5: Execute Commits Sequentially

For each planned commit:

1. Run `git add <file1> <file2> ...` to stage only the files for that commit
2. Run `git commit -m "<conventional commit message>"`
3. Wait for confirmation before proceeding to next commit

### Step 6: Show Final Summary

After all commits are complete, display:

1. Run `git status` to confirm working tree is clean
2. Run `git log --oneline -N` (where N = number of commits made)
3. Present a summary table:

```
## Commit Breakdown Complete!

| # | Commit Hash | Description | Files |
|---|-------------|-------------|-------|
| 1 | abc1234 | feat(scope): description | 3 |
| 2 | def5678 | feat(scope): description | 4 |
...

**Total: X files in Y commits** on branch `branch-name`

Ready to push!
```

## Conventional Commit Format

Use this format for commit messages:

- `feat(scope):` - New feature
- `fix(scope):` - Bug fix
- `refactor(scope):` - Code refactoring
- `test(scope):` - Adding/updating tests
- `docs(scope):` - Documentation changes
- `chore(scope):` - Maintenance tasks
- `style(scope):` - Formatting changes

The scope should be derived from the feature/module being modified (e.g., `card-provider`, `kyc`, `wallet`, `admin`).

## Example Groupings

**Good groupings:**

- Migration + Model + Factory (database foundation)
- Command + Handler (application layer pair)
- FormRequest classes together (validation layer)
- Controller + Resource (HTTP layer pair)
- Routes + Tests (integration layer)

**Avoid:**

- Mixing unrelated features in one commit
- Separating tightly coupled files (e.g., interface from implementation)
- Commits with only 1 file when logical grouping exists

## Edge Cases

- **Single file changes**: Commit individually with appropriate message
- **Very large changesets (50+ files)**: Warn user and suggest reviewing if grouping makes sense
- **Mix of features**: Ask user to clarify which feature each file belongs to if unclear
- **Deleted files**: Group with related modifications or in separate "cleanup" commit
