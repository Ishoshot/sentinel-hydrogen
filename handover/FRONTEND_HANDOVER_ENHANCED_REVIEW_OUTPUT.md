# Frontend Implementation: Enhanced Review Output

> **This is a handover document for the frontend agent. Delete this file after use.**

## Context

The backend review system has been enhanced with richer AI-generated output:

1. **Summary** now includes verdict, strengths, and concerns
2. **Findings** now include code replacement suggestions with explanation

This enables the frontend to display more actionable, copy-paste-ready code fixes.

---

## What Changed

### Run Summary (New Fields)

The `summary` field in the Run API response now includes:

| Field | Type | Description |
|-------|------|-------------|
| `overview` | `string` | Comprehensive analysis of the PR (can be multi-paragraph) |
| `verdict` | `string` | AI's recommendation: `approve`, `request_changes`, or `comment` |
| `risk_level` | `string` | Overall risk: `low`, `medium`, `high`, or `critical` |
| `strengths` | `string[]` | Positive aspects of the implementation |
| `concerns` | `string[]` | High-level concerns or risks |
| `recommendations` | `string[]` | Actionable next steps |

### Finding Metadata (New Fields)

The `metadata` field on findings now includes code replacement data:

| Field | Type | Description |
|-------|------|-------------|
| `current_code` | `string` | The problematic code snippet |
| `replacement_code` | `string` | The suggested fix (copy-paste ready) |
| `explanation` | `string` | Why the replacement is better |
| `rationale` | `string` | Impact/consequence if not fixed |
| `references` | `string[]` | CWE numbers, OWASP refs, best practices |

Legacy fields (`suggestion`, `patch`, `tags`) are still supported for backward compatibility.

---

## API Response Examples

### Run with Enhanced Summary

```
GET /api/workspaces/{workspace_id}/runs/{run_id}
```

```json
{
  "data": {
    "id": 42,
    "repository_id": 4,
    "status": "completed",
    "started_at": "2026-01-11T10:00:00.000000Z",
    "completed_at": "2026-01-11T10:00:45.000000Z",
    "metrics": {
      "files_changed": 5,
      "lines_added": 120,
      "lines_deleted": 45,
      "tokens_used_estimated": 15420,
      "model": "claude-sonnet-4-20250514",
      "provider": "anthropic",
      "duration_ms": 12450
    },
    "pull_request": {
      "number": 123,
      "title": "Add user authentication flow",
      "base_branch": "main",
      "head_branch": "feature/auth",
      "head_sha": "abc123def",
      "is_draft": false,
      "author": {
        "login": "developer",
        "avatar_url": "https://..."
      },
      "labels": []
    },
    "summary": {
      "overview": "This PR implements a user authentication flow using Laravel Sanctum. The implementation follows security best practices with proper token handling and session management. However, there are a few areas that need attention before merging.\n\nThe authentication controller is well-structured, but the password validation could be strengthened. The token expiration logic is correctly implemented.",
      "verdict": "request_changes",
      "risk_level": "medium",
      "strengths": [
        "Clean separation of authentication logic into dedicated controller",
        "Proper use of Laravel Sanctum for API token management",
        "Good error handling with appropriate HTTP status codes"
      ],
      "concerns": [
        "Password validation allows weak passwords",
        "Missing rate limiting on login endpoint"
      ],
      "recommendations": [
        "Add password complexity requirements (min 12 chars, mixed case, numbers)",
        "Implement rate limiting using Laravel's ThrottleRequests middleware",
        "Add audit logging for failed login attempts"
      ]
    },
    "findings": [
      {
        "id": 101,
        "severity": "high",
        "category": "security",
        "title": "Weak password validation allows easily guessable passwords",
        "description": "The current password validation only requires a minimum of 6 characters, which is insufficient for modern security standards.",
        "file_path": "app/Http/Requests/RegisterRequest.php",
        "line_start": 24,
        "line_end": 28,
        "confidence": 0.95,
        "metadata": {
          "rationale": "Weak passwords are the leading cause of account compromises. An attacker could easily brute-force or guess short passwords, gaining unauthorized access to user accounts.",
          "current_code": "'password' => ['required', 'string', 'min:6', 'confirmed'],",
          "replacement_code": "'password' => [\n    'required',\n    'string',\n    'min:12',\n    'regex:/[a-z]/',\n    'regex:/[A-Z]/',\n    'regex:/[0-9]/',\n    'confirmed',\n],",
          "explanation": "This adds minimum 12 character length and requires at least one lowercase letter, one uppercase letter, and one number. Consider using Laravel's Password::min(12)->mixedCase()->numbers() rule for cleaner syntax.",
          "references": [
            "OWASP-A07:2021",
            "CWE-521"
          ]
        },
        "created_at": "2026-01-11T10:00:45.000000Z"
      },
      {
        "id": 102,
        "severity": "medium",
        "category": "security",
        "title": "Login endpoint lacks rate limiting",
        "description": "The login endpoint does not implement rate limiting, making it vulnerable to brute-force attacks.",
        "file_path": "routes/api.php",
        "line_start": 15,
        "line_end": 15,
        "confidence": 0.92,
        "metadata": {
          "rationale": "Without rate limiting, attackers can attempt unlimited password guesses, potentially compromising accounts through brute-force attacks.",
          "current_code": "Route::post('/login', [AuthController::class, 'login']);",
          "replacement_code": "Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');",
          "explanation": "This limits login attempts to 5 per minute per IP address. Adjust the values based on your expected legitimate traffic patterns.",
          "references": [
            "OWASP-A07:2021",
            "CWE-307"
          ]
        },
        "created_at": "2026-01-11T10:00:45.000000Z"
      }
    ],
    "created_at": "2026-01-11T10:00:00.000000Z"
  }
}
```

---

## TypeScript Interfaces

Update your type definitions:

```typescript
// Run summary with new fields
interface RunSummary {
  overview: string;
  verdict: 'approve' | 'request_changes' | 'comment';
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  strengths: string[];
  concerns: string[];
  recommendations: string[];
}

// Finding with enhanced metadata
interface Finding {
  id: number;
  run_id: number;
  severity: 'info' | 'low' | 'medium' | 'high' | 'critical';
  category: 'security' | 'correctness' | 'reliability' | 'performance' | 'maintainability' | 'testing' | 'documentation';
  title: string;
  description: string;
  file_path: string | null;
  line_start: number | null;
  line_end: number | null;
  confidence: number | null;
  metadata: FindingMetadata | null;
  annotations: Annotation[];
  created_at: string;
}

// New: Finding metadata with code suggestions
interface FindingMetadata {
  // Code replacement (new)
  current_code?: string;
  replacement_code?: string;
  explanation?: string;

  // Impact/rationale
  rationale?: string;

  // References
  references?: string[];

  // Legacy fields (backward compatibility)
  suggestion?: string;
  patch?: string;
  tags?: string[];
}

// Updated Run interface
interface Run {
  id: number;
  repository_id: number;
  external_reference: string;
  status: 'queued' | 'in_progress' | 'completed' | 'failed' | 'skipped';
  started_at: string | null;
  completed_at: string | null;
  metrics: RunMetrics | null;
  policy_snapshot: Record<string, unknown> | null;
  pull_request: PullRequestInfo | null;
  summary: RunSummary | null;
  metadata: Record<string, unknown> | null;
  repository?: Repository;
  findings?: Finding[];
  findings_count?: number;
  created_at: string;
}

interface RunMetrics {
  files_changed: number;
  lines_added: number;
  lines_deleted: number;
  tokens_used_estimated: number;
  model: string;
  provider: string;
  duration_ms: number;
}
```

---

## UI Components Needed

### 1. Review Summary Card

Display the AI's overall assessment prominently at the top of the run detail page.

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Review Summary                                          [Request Changes]│
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ This PR implements a user authentication flow using Laravel Sanctum...  │
│ (full overview text, can be multi-paragraph)                            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ ✓ Strengths                        ⚠ Concerns                           │
│ • Clean separation of auth logic   • Password validation allows weak... │
│ • Proper use of Laravel Sanctum    • Missing rate limiting on login...  │
│ • Good error handling              │                                    │
├─────────────────────────────────────────────────────────────────────────┤
│ → Recommendations                                                       │
│ • Add password complexity requirements (min 12 chars, mixed case...)    │
│ • Implement rate limiting using Laravel's ThrottleRequests middleware   │
│ • Add audit logging for failed login attempts                           │
└─────────────────────────────────────────────────────────────────────────┘
```

**Verdict Badge Colors:**
| Verdict | Color | Icon |
|---------|-------|------|
| `approve` | Green | ✓ Check |
| `request_changes` | Orange/Amber | ⚠ Warning |
| `comment` | Blue/Gray | 💬 Comment |

**Risk Level Indicators:**
| Level | Color |
|-------|-------|
| `low` | Green |
| `medium` | Yellow |
| `high` | Orange |
| `critical` | Red |

### 2. Finding Card with Code Diff

Show the problematic code alongside the suggested fix.

```
┌─────────────────────────────────────────────────────────────────────────┐
│ [HIGH] Security                                                         │
│ Weak password validation allows easily guessable passwords              │
├─────────────────────────────────────────────────────────────────────────┤
│ app/Http/Requests/RegisterRequest.php:24-28                             │
├─────────────────────────────────────────────────────────────────────────┤
│ The current password validation only requires a minimum of 6            │
│ characters, which is insufficient for modern security standards.        │
├─────────────────────────────────────────────────────────────────────────┤
│ Impact                                                                  │
│ Weak passwords are the leading cause of account compromises...          │
├─────────────────────────────────────────────────────────────────────────┤
│ Current Code                                     [Copy]                 │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 'password' => ['required', 'string', 'min:6', 'confirmed'],         │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Suggested Fix                                    [Copy]                 │
│ ┌─────────────────────────────────────────────────────────────────────┐ │
│ │ 'password' => [                                                     │ │
│ │     'required',                                                     │ │
│ │     'string',                                                       │ │
│ │     'min:12',                                                       │ │
│ │     'regex:/[a-z]/',                                                │ │
│ │     'regex:/[A-Z]/',                                                │ │
│ │     'regex:/[0-9]/',                                                │ │
│ │     'confirmed',                                                    │ │
│ │ ],                                                                  │ │
│ └─────────────────────────────────────────────────────────────────────┘ │
│                                                                         │
│ Why This Fix?                                                           │
│ This adds minimum 12 character length and requires at least one         │
│ lowercase letter, one uppercase letter, and one number...               │
├─────────────────────────────────────────────────────────────────────────┤
│ References: OWASP-A07:2021, CWE-521                                     │
└─────────────────────────────────────────────────────────────────────────┘
```

### 3. Code Block Component

Create a reusable code block with:
- Syntax highlighting (use `file_path` extension to determine language)
- Copy button
- Line numbers (optional)
- Diff view option (show current vs replacement side-by-side or inline)

```vue
<template>
  <div class="code-block">
    <div class="code-header">
      <span class="code-label">{{ label }}</span>
      <button @click="copyCode" class="copy-btn">
        {{ copied ? '✓ Copied' : 'Copy' }}
      </button>
    </div>
    <pre><code :class="languageClass">{{ code }}</code></pre>
  </div>
</template>
```

---

## Display Logic

### When to Show Code Suggestions

Only show the code diff section if `metadata.replacement_code` exists:

```typescript
const hasCodeSuggestion = computed(() => {
  return finding.metadata?.replacement_code != null;
});
```

### Fallback for Legacy Findings

Some older findings may use `suggestion` instead of `replacement_code`:

```typescript
const getSuggestion = (finding: Finding): string | null => {
  return finding.metadata?.replacement_code
    ?? finding.metadata?.suggestion
    ?? null;
};
```

### Reference Links

Map references to URLs when possible:

```typescript
const getReferenceUrl = (ref: string): string | null => {
  if (ref.startsWith('CWE-')) {
    const id = ref.replace('CWE-', '');
    return `https://cwe.mitre.org/data/definitions/${id}.html`;
  }
  if (ref.startsWith('OWASP-')) {
    return 'https://owasp.org/Top10/';
  }
  return null;
};
```

---

## UX Recommendations

1. **Verdict is Primary** - Show the verdict badge prominently; it's the TL;DR of the review

2. **Code Suggestions are Actionable** - Make the copy button obvious; developers should be able to apply fixes with one click

3. **Collapse Long Code** - If `current_code` or `replacement_code` exceeds ~10 lines, collapse by default with "Show more"

4. **Highlight Differences** - Consider using a diff library to highlight changes between `current_code` and `replacement_code`

5. **Empty States**:
   - No strengths: Don't show the strengths section
   - No concerns: Show "No concerns identified"
   - No code suggestion: Show description and rationale only

6. **Mobile Friendly** - Code blocks should scroll horizontally on mobile

---

## Quick Start Checklist

- [ ] Update TypeScript interfaces with new fields
- [ ] Create `ReviewSummaryCard` component with verdict, strengths, concerns
- [ ] Create `CodeSuggestion` component with current/replacement code blocks
- [ ] Add copy-to-clipboard functionality for code blocks
- [ ] Update `FindingCard` to show code suggestions when available
- [ ] Add reference link generation for CWE/OWASP
- [ ] Handle backward compatibility for legacy `suggestion` field
- [ ] Test with runs that have no summary (queued/failed status)

---

## Files Changed (Backend)

For reference, these backend files were modified:

| File | Change |
|------|--------|
| `app/Http/Resources/RunResource.php` | Added `verdict`, `strengths`, `concerns` to summary |
| `app/Services/Reviews/PrismReviewEngine.php` | Parses new AI output fields |
| `app/Actions/Reviews/ExecuteReviewRun.php` | Stores new metadata fields on findings |
| `resources/views/prompts/review-system.blade.php` | Enhanced prompt with expert persona |
| `resources/views/prompts/domains/*.blade.php` | New domain-specific review expertise |

---

Backend is ready for frontend integration.
