## Pull Request Details

**Repository:** {{ $pull_request['repository_full_name'] }}
**PR #{{ $pull_request['number'] }}:** {{ $pull_request['title'] }}
**Author:** {{ $pull_request['sender_login'] }}
**Base Branch:** {{ $pull_request['base_branch'] }}
**Head Branch:** {{ $pull_request['head_branch'] }}
**Commit:** {{ $pull_request['head_sha'] }}

@if($pull_request['body'])
### Description

{{ $pull_request['body'] }}
@endif

## Change Statistics

- Files Changed: {{ $metrics['files_changed'] }}
- Lines Added: {{ $metrics['lines_added'] }}
- Lines Deleted: {{ $metrics['lines_deleted'] }}

## Changed Files

@foreach($files as $file)
### {{ $file['filename'] }}
- Additions: {{ $file['additions'] }}
- Deletions: {{ $file['deletions'] }}
- Changes: {{ $file['changes'] }}

@endforeach

@if(isset($file_contents) && count($file_contents) > 0)
## File Contents

@foreach($file_contents as $path => $content)
### {{ $path }}

```
{{ $content }}
```

@endforeach
@endif

## Review Request

Please review this pull request and provide your analysis in the specified JSON format. Focus on:

1. Security vulnerabilities
2. Correctness issues
3. Reliability concerns
4. Performance problems
5. Maintainability issues

If the changes look good and you have no significant findings, return an empty findings array with a positive summary.
