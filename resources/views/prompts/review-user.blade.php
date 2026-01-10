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
## Previous Reviews

This PR has been reviewed {{ count($review_history) }} time(s) before by Sentinel.

@foreach($review_history as $review)
### Review from {{ $review['created_at'] ?? 'earlier' }}
{{ $review['summary'] }}

@if(isset($review['key_findings']) && count($review['key_findings']) > 0)
**Key findings from previous review:**
@foreach($review['key_findings'] as $finding)
- [{{ strtoupper($finding['severity']) }}] {{ $finding['title'] }}@if($finding['file_path']) in `{{ $finding['file_path'] }}`@endif

@endforeach
@endif
@endforeach

**Important:** Check if issues from previous reviews have been addressed. If they haven't, include them in your findings with a note that they persist from earlier review(s).

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
