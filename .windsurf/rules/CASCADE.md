<laravel-boost-guidelines>
=== .ai/code-review-quality rules ===

---
trigger: always_on
---

# Code Review & Quality

## Self-Review Before Completion

After writing or modifying code, **pause and critically review your own work** before considering the task done. Do not assume correctness — verify it. Specifically check for:

- **Logic bugs:** Does every method do exactly what its name and docblock promise? Trace through edge cases mentally.
- **Blast radius:** Does a method that should affect one record accidentally affect many? Does a query scope filter tightly enough?
- **Dependency correctness:** When action A calls action B, does B's behavior match what A expects in all cases? Look for mismatches in scope, side effects, or return values.
- **Race conditions:** If this code runs concurrently (e.g., queued jobs, webhook retries), does it still behave correctly?
- **State transitions:** Are only the intended records transitioned? Could adjacent or unrelated records be caught in the blast?

If you find an issue during self-review, fix it immediately — do not leave it for the user to catch.

## Code Review (Required)

Before considering any code-writing task complete, invoke the **code-reviewer** agent to review your changes. This applies when you've created new files or made non-trivial edits.

**Review cycle:**

1. Run the code-reviewer agent after completing your changes
2. If the reviewer identifies issues that need fixing, fix them
3. Re-run the code-reviewer to verify the fixes are correct (very important)
4. Repeat until the reviewer passes with no critical issues
5. It is important that you check in with the reviewer after addressing issues it raised (do not ignore this)
6. If you're running in claude code web/remote ensure this is done before pushing changes to git remote
7. When re-running the code-reviewer to verify fixes, if you attempt to resume the agent and you get an error, spawn a new agent instead

The task is only complete when the code-reviewer confirms the code is acceptable.

=== .ai/sentinel-context rules ===

---
trigger: always_on
---

> **Terminal title.** Update the iTerm title whenever the session topic changes using OSC sequences; use ⚡︎ for Codex. Run: `printf '\033]0;%s\007' "⚡︎ Topic" > "$CODEX_TTY"` where "Topic" is a short (2-4 word) description of the current task.

# Sentinel – Backend Context

## Documentation Contracts

The `/docs` folder contains authoritative contracts for this codebase. **Before modifying core features, read the relevant doc based on task type:**

| Task                            | Read                                   |
| ------------------------------- | -------------------------------------- |
| Models, migrations, queries     | `docs/backend/DATA_MODEL.md`           |
| Actions, services, jobs, events | `docs/backend/BACKEND_ARCHITECTURE.md` |
| Auth, tokens, permissions       | `docs/backend/AUTHENTICATION.md`       |
| Review flow, state transitions  | `docs/backend/REVIEW_STATE_MACHINE.md` |
| Queues, job dispatch            | `docs/backend/QUEUE_AND_JOBS.md`       |
| Briefings feature               | `docs/backend/BRIEFINGS.md`            |
| External APIs, GitHub, Polar    | `docs/backend/INTEGRATIONS.md`         |
| Code style, conventions         | `docs/backend/CODING_STANDARDS.md`     |
| Backend refactor yardstick      | `docs/backend/LARAVEL_REFACTOR_YARDSTICK.md` |
| Tests                           | `docs/backend/TESTING_STRATEGY.md`     |
| Sentinel config schema          | `docs/SENTINEL_CONFIG.md`              |
| Product scope, goals            | `docs/product/PRD.md`                  |
| Naming, terminology             | `docs/product/GLOSSARY.md`             |
| Billing, limits, plans          | `docs/product/PLANS_AND_LIMITS.md`     |
| UX behavior                     | `docs/product/UX_PRINCIPLES.md`        |

**Conflict handling:** If a change would violate a documented contract, stop and ask. Offer to update the doc if the change is intentional.

## Core Rules

- All data scoped to `workspace_id`
- Controllers delegate to Actions (no business logic in controllers)
- Actions orchestrate; Services encapsulate focused logic
- Jobs are idempotent and retry-safe
- Use exact terms from `GLOSSARY.md` (Workspace, Run, Finding, Member)
- Use `docs/backend/LARAVEL_REFACTOR_YARDSTICK.md` as the quality bar for backend refactors

## Tooling

Laravel Pint (formatting), Pest (tests), Rector (refactoring)

## Principle

Clarity over cleverness. Correctness over speed. If uncertain, ask.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.17
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/socialite (SOCIALITE) - v5
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `developing-with-prism` — Guide for developing with Prism PHP package - a Laravel package for integrating LLMs. Activate or use when working with Prism features including text generation, structured output, embeddings, image generation, audio processing, streaming, tools/function calling, or any LLM provider integration (OpenAI, Anthropic, Gemini, Mistral, Groq, XAI, DeepSeek, OpenRouter, Ollama, VoyageAI, ElevenLabs). Activate for any Prism-related development tasks.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- That being said, keys in an Enum should follow existing application Enum conventions.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== echolabsdev/prism rules ===

## Prism

- Prism is a Laravel package for integrating Large Language Models (LLMs) into applications with a fluent, expressive and eloquent API.
- IMPORTANT: Activate `developing-with-prism` skill when working with Prism features.

</laravel-boost-guidelines>
