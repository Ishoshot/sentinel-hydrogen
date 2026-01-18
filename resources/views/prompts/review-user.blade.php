## Pull Request Details

**Repository:** {{ $pull_request['repository_full_name'] }}
**PR #{{ $pull_request['number'] }}:** {{ $pull_request['title'] }}
**Author:** {{ $pull_request['author']['login'] ?? $pull_request['sender_login'] ?? 'Unknown' }}
**Base Branch:** {{ $pull_request['base_branch'] }} ← **Head Branch:** {{ $pull_request['head_branch'] }}
**Commit:** {{ $pull_request['head_sha'] }}
@if($pull_request['is_draft'] ?? false)
**Status:** Draft
@endif

@if($pull_request['body'])
### Description

{{ $pull_request['body'] }}
@endif

@if(isset($pull_request['labels']) && count($pull_request['labels']) > 0)
**Labels:** @foreach($pull_request['labels'] as $label){{ $label['name'] }}@if(!$loop->last), @endif @endforeach

@endif

@if(isset($linked_issues) && count($linked_issues) > 0)
## Linked Issues

@foreach($linked_issues as $issue)
### Issue #{{ $issue['number'] }}: {{ $issue['title'] }}
**State:** {{ $issue['state'] }}@if(count($issue['labels'] ?? []) > 0) | **Labels:** {{ implode(', ', $issue['labels']) }}@endif

{{ $issue['body'] }}

@if(isset($issue['comments']) && count($issue['comments']) > 0)
#### Issue Discussion
@foreach($issue['comments'] as $comment)
> **{{ $comment['author'] }}:** {{ $comment['body'] }}
@endforeach
@endif

@endforeach
@endif

@if(isset($pr_comments) && count($pr_comments) > 0)
## PR Discussion

@foreach($pr_comments as $comment)
> **{{ $comment['author'] }}** ({{ $comment['created_at'] ?? '' }}): {{ $comment['body'] }}
@endforeach

@endif

@if(isset($review_history) && count($review_history) > 0)
## Previous Reviews (IMPORTANT - Check for Resolution)

⚠️ This PR has been reviewed **{{ count($review_history) }} time(s)** before. You MUST check if previous findings were addressed.

@foreach($review_history as $reviewIndex => $review)
### Review #{{ $reviewIndex + 1 }} — {{ $review['created_at'] ?? 'earlier' }}
{{ $review['summary'] }}

@if(isset($review['key_findings']) && count($review['key_findings']) > 0)
**Findings to check (were they fixed?):**

| Status | Severity | Finding | Location |
|--------|----------|---------|----------|
@foreach($review['key_findings'] as $finding)
| ❓ | **{{ strtoupper($finding['severity']) }}** | {{ $finding['title'] }} | `{{ $finding['file_path'] ?? 'N/A' }}`@if(isset($finding['line_start'])):{{ $finding['line_start'] }}@endif |
@endforeach

@endif
@endforeach

**Your task:**
1. Check the current diff - did the developer fix any of these issues?
2. For **fixed issues**: Acknowledge in your overview (e.g., "✓ SQL injection from previous review has been fixed")
3. For **unfixed issues**: Reference them as persistent (e.g., "The N+1 query I flagged before is still present")
4. **Do NOT re-report identical findings** - just note they persist

@endif

@if(isset($guidelines) && count($guidelines) > 0)
## Team Guidelines (MANDATORY)

⚠️ **These are project-specific rules defined by the repository maintainers. They carry the same weight as security and correctness checks.**

The following documents define how code should be written in this repository. Violations of these guidelines are findings, not suggestions.

@foreach($guidelines as $guideline)
### 📄 `{{ $guideline['path'] }}`@if($guideline['description']) — {{ $guideline['description'] }}@endif

{{ $guideline['content'] }}

---

@endforeach

**How to use these guidelines:**
- Read each guideline document carefully before reviewing the code
- When code violates a guideline, **quote the specific rule** in your finding using blockquotes
- Reference the guideline file: `{{ $guideline['path'] ?? 'guideline' }}` in your finding
- If the code follows the guidelines well, mention it as a strength

**Example citation format:**
> `architecture-guide.md` states: "All domain logic must reside in the Domain layer, never in Controllers."
>
> The `UserController.php` **lines 45-60** violates this by calculating discounts directly in the controller.

@endif
@if(isset($repository_context) && (($repository_context['readme'] ?? null) || ($repository_context['contributing'] ?? null)))
## Repository Context

@if(isset($repository_context['contributing']) && $repository_context['contributing'])
### Contributing Guidelines

<details>
<summary>Click to expand CONTRIBUTING.md</summary>

{{ $repository_context['contributing'] }}

</details>

@endif
@if(isset($repository_context['readme']) && $repository_context['readme'])
### Project Overview (README)

<details>
<summary>Click to expand README.md</summary>

{{ $repository_context['readme'] }}

</details>

@endif
@endif

@if(isset($sensitive_files) && count($sensitive_files) > 0)
## Security-Sensitive Files

The following files in this PR are flagged as security-sensitive and require extra scrutiny:

@foreach($sensitive_files as $file)
- `{{ $file }}`
@endforeach

**Important:** Apply stricter security checks to these files. Look for authentication bypasses, authorization flaws, credential exposure, injection vulnerabilities, and other security issues with heightened vigilance.

@endif
## Change Statistics

- **Files Changed:** {{ $metrics['files_changed'] }}
- **Lines Added:** +{{ $metrics['lines_added'] }}
- **Lines Deleted:** -{{ $metrics['lines_deleted'] }}

## Code Changes

@foreach($files as $file)
### {{ $file['filename'] }} ({{ $file['status'] ?? 'modified' }})
`+{{ $file['additions'] }} -{{ $file['deletions'] }}`

@if(!empty($file['patch']))
```diff
{{ $file['patch'] }}
```
@else
_Binary file or no diff available_
@endif

@endforeach

@if(isset($file_contents) && count($file_contents) > 0)
## Full File Context

The following files are provided in full for better understanding of the changes:

@foreach($file_contents as $path => $content)
### 📄 `{{ $path }}`

```{{ pathinfo($path, PATHINFO_EXTENSION) }}
{{ $content }}
```

@endforeach
@endif

@if(isset($semantics) && count($semantics) > 0)
## Semantic Analysis

The following structural information was extracted from the changed files using static analysis:

@foreach($semantics as $filename => $data)
### `{{ $filename }}`
**Language:** {{ $data['language'] ?? 'unknown' }}

@if(isset($data['functions']) && count($data['functions']) > 0)
**Functions:**
@foreach($data['functions'] as $func)
- `{{ $func['name'] }}({{ implode(', ', array_column($func['parameters'] ?? [], 'name')) }})` @ lines {{ $func['line_start'] }}-{{ $func['line_end'] }}@if(!empty($func['return_type'])) → `{{ $func['return_type'] }}`@endif
@endforeach

@endif
@if(isset($data['classes']) && count($data['classes']) > 0)
**Classes:**
@foreach($data['classes'] as $class)
- `{{ $class['name'] }}`{{ !empty($class['extends']) ? " extends `{$class['extends']}`" : '' }}{{ !empty($class['implements']) ? ' implements ' . implode(', ', array_map(fn($i) => "`$i`", $class['implements'])) : '' }} @ lines {{ $class['line_start'] }}-{{ $class['line_end'] }}
  @if(isset($class['methods']) && count($class['methods']) > 0)
  - Methods: @foreach($class['methods'] as $method)`{{ $method['name'] }}()`@if(!$loop->last), @endif @endforeach

  @endif
@endforeach

@endif
@if(isset($data['imports']) && count($data['imports']) > 0)
**Imports ({{ count($data['imports']) }}):**
@foreach(array_slice($data['imports'], 0, 10) as $import)
- `{{ $import['module'] }}`@if(!empty($import['symbols'])) ({{ implode(', ', $import['symbols']) }})@endif

@endforeach
@if(count($data['imports']) > 10)
_... and {{ count($data['imports']) - 10 }} more imports_
@endif

@endif
@if(isset($data['calls']) && count($data['calls']) > 0)
**Function Calls ({{ count($data['calls']) }} total):**
@foreach(array_slice($data['calls'], 0, 10) as $call)
- Line {{ $call['line'] }}: `{{ $call['callee'] }}()`@if($call['is_method_call'] && !empty($call['receiver'])) on `{{ $call['receiver'] }}`@endif

@endforeach
@if(count($data['calls']) > 10)
_... and {{ count($data['calls']) - 10 }} more calls_
@endif

@endif
@if(isset($data['errors']) && count($data['errors']) > 0)
**⚠️ Syntax Errors Detected:**
@foreach($data['errors'] as $error)
- Line {{ $error['line'] }}: {{ $error['message'] }}
@endforeach

@endif
@endforeach

**Use semantic analysis to:**
- Verify call chains and dependencies
- Detect type mismatches when return types change
- Identify unused imports or missing imports
- Check if removed functions are still being called
- Validate class inheritance and interface implementations

@endif

## Review Request

Please review this pull request and provide your analysis in the specified JSON format. Focus on:

1. **Security vulnerabilities** - injection, authentication, authorization issues
2. **Correctness issues** - logic errors, incorrect behavior
3. **Reliability concerns** - error handling, edge cases, failure modes
4. **Performance problems** - inefficiencies, resource usage
5. **Maintainability issues** - code organization, complexity, readability

@if(isset($linked_issues) && count($linked_issues) > 0)
**Important:** Verify that the code changes properly address the linked issue(s). Flag if the implementation diverges from the issue requirements.
@endif

If the changes look good and you have no significant findings, provide a summary that explains what the PR accomplishes and why the implementation is sound. Include "LGTM" in your overview.
