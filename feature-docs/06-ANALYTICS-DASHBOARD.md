# Analytics Dashboard

## Measuring What Matters

You can't improve what you don't measure. The Analytics Dashboard transforms Sentinel's review data into actionable insights about your team's code quality, review patterns, and engineering health.

But here's the thing—we don't just dump numbers on you. Every metric is designed to answer a real question that engineering leaders actually ask.

---

## Dashboard Philosophy

### What We Don't Do

❌ Vanity metrics that look good but mean nothing
❌ Overwhelming dashboards with 50 widgets
❌ Metrics that encourage gaming the system
❌ Comparisons that pit developers against each other

### What We Do

✅ Trend-focused insights (are we improving?)
✅ Team-level metrics (collective health)
✅ Actionable recommendations
✅ Context for every number

---

## Analytics Endpoints

Sentinel provides 12 specialized analytics endpoints, each answering a specific question:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           ANALYTICS ENDPOINTS                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  OVERVIEW                                                                       │
│  /overview-metrics        "How are we doing overall?"                          │
│                                                                                  │
│  QUALITY                                                                        │
│  /findings-distribution   "What types of issues are we finding?"               │
│  /top-categories          "What are our biggest problem areas?"                │
│  /quality-score-trend     "Is our code quality improving?"                     │
│  /resolution-rate         "Are we fixing the issues we find?"                  │
│                                                                                  │
│  VELOCITY                                                                       │
│  /review-velocity         "How fast are we reviewing code?"                    │
│  /review-duration-trends  "Are reviews getting faster or slower?"              │
│  /run-activity-timeline   "When are reviews happening?"                        │
│  /success-rate            "How often do reviews complete successfully?"        │
│                                                                                  │
│  PEOPLE                                                                         │
│  /developer-leaderboard   "Who's contributing the most?"                       │
│  /repository-activity     "Which repos are most active?"                       │
│                                                                                  │
│  RESOURCES                                                                      │
│  /token-usage             "How much AI capacity are we using?"                 │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Overview Metrics

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/overview-metrics`

The big picture at a glance:

```json
{
  "data": {
    "period": {
      "start": "2026-01-01",
      "end": "2026-01-31"
    },
    "runs": {
      "total": 342,
      "completed": 298,
      "failed": 12,
      "skipped": 32,
      "completion_rate": 87.1
    },
    "findings": {
      "total": 1247,
      "by_severity": {
        "critical": 3,
        "high": 45,
        "medium": 389,
        "low": 810
      }
    },
    "repositories": {
      "active": 8,
      "total_enabled": 12
    },
    "trends": {
      "runs_vs_previous": 15.2,
      "findings_vs_previous": -8.4,
      "critical_vs_previous": -66.7
    }
  }
}
```

### What This Tells You

| Metric | Insight |
|--------|---------|
| Completion rate 87.1% | Most reviews are completing successfully |
| Critical findings: 3 | Security is under control |
| Findings down 8.4% | Code quality is improving |
| Critical down 66.7% | Major issues are being addressed |

---

## Findings Distribution

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/findings-distribution`

Understand what kinds of issues Sentinel is finding:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     FINDINGS DISTRIBUTION                                        │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  BY SEVERITY                          BY CATEGORY                               │
│  ┌─────────────────────────┐          ┌─────────────────────────────────┐      │
│  │ Critical  ███ 3%        │          │ Security        ████████ 18%    │      │
│  │ High      ██████ 12%    │          │ Performance     ███████████ 25% │      │
│  │ Medium    ████████ 28%  │          │ Maintainability ████████████ 35%│      │
│  │ Low       ████████████ 57% │        │ Correctness     ████████ 22%    │      │
│  └─────────────────────────┘          └─────────────────────────────────┘      │
│                                                                                  │
│  TREND                                                                          │
│  Critical and high-severity findings have decreased 23% compared to last       │
│  month, while maintainability findings remain steady.                           │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Response Format

```json
{
  "data": {
    "by_severity": [
      { "severity": "critical", "count": 3, "percentage": 0.24 },
      { "severity": "high", "count": 45, "percentage": 3.61 },
      { "severity": "medium", "count": 389, "percentage": 31.19 },
      { "severity": "low", "count": 810, "percentage": 64.96 }
    ],
    "by_category": [
      { "category": "maintainability", "count": 436, "percentage": 34.96 },
      { "category": "performance", "count": 312, "percentage": 25.02 },
      { "category": "correctness", "count": 275, "percentage": 22.05 },
      { "category": "security", "count": 224, "percentage": 17.97 }
    ]
  }
}
```

---

## Quality Score Trend

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/quality-score-trend`

A composite score tracking code quality over time:

```
Quality Score: 0-100 (higher is better)

Factors:
├─ Finding density (findings per 1000 lines changed)
├─ Severity distribution (lower severity = better)
├─ Resolution rate (addressed findings)
└─ Repeat issues (same findings recurring)
```

### Visualization

```
    Quality Score Over Time

100 ┤
 90 ┤                                        ●━━━●
 80 ┤                        ●━━━●━━━●━━━●━━━●
 70 ┤        ●━━━●━━━●━━━●━━━●
 60 ┤   ●━━━●
 50 ┤━━━●
    └────────────────────────────────────────────────
      Week 1   2    3    4    5    6    7    8    9

    ↑ Score improved from 52 to 88 over 9 weeks
```

---

## Developer Leaderboard

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/developer-leaderboard`

Celebrate contributions without creating competition:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     DEVELOPER CONTRIBUTIONS                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  🏅 TOP CONTRIBUTORS (by PRs reviewed)                                          │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  1. Alice Smith      47 PRs    ████████████████████                    │    │
│  │  2. Bob Johnson      38 PRs    ████████████████                        │    │
│  │  3. Carol Williams   31 PRs    █████████████                           │    │
│  │  4. David Brown      28 PRs    ████████████                            │    │
│  │  5. Eve Davis        24 PRs    ██████████                              │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│  ⚡ MOST IMPROVED (quality score gain)                                         │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  1. Frank Miller     +32 points (now 78)                               │    │
│  │  2. Grace Lee        +28 points (now 85)                               │    │
│  │  3. Henry Wilson     +21 points (now 72)                               │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│  Note: Leaderboard shows contribution volume, not performance ranking.          │
│  All team members are valued contributors regardless of position.               │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Philosophy

We intentionally don't:
- Rank developers by "quality"
- Show who creates the most bugs
- Create pressure through comparison

We do show:
- Volume of contributions
- Improvement over time
- Participation in the review process

---

## Review Velocity

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/review-velocity`

How fast are reviews completing?

```json
{
  "data": {
    "average_duration_seconds": 45,
    "median_duration_seconds": 32,
    "p95_duration_seconds": 120,
    "by_size": {
      "small": { "avg_seconds": 22, "count": 156 },
      "medium": { "avg_seconds": 48, "count": 98 },
      "large": { "avg_seconds": 95, "count": 34 }
    },
    "queue_time": {
      "average_seconds": 3,
      "max_seconds": 15
    }
  }
}
```

### What Affects Velocity

| Factor | Impact on Speed |
|--------|-----------------|
| PR size | Larger PRs = slower reviews |
| Queue depth | More queued jobs = longer wait |
| AI provider | Different response times |
| Context complexity | More files = more processing |

---

## Review Duration Trends

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/review-duration-trends`

Are reviews getting faster or slower over time?

```
    Review Duration Trend (seconds)

120 ┤●
100 ┤  ●
 80 ┤    ●━━━●
 60 ┤          ●━━━●━━━●
 40 ┤                    ●━━━●━━━●━━━●
 20 ┤
    └────────────────────────────────────────────
      Jan    Feb    Mar    Apr    May    Jun

    ↓ Average review time decreased from 115s to 38s

    Likely causes:
    ├─ Better context caching
    ├─ Optimized collector pipeline
    └─ Smaller average PR size
```

---

## Repository Activity

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/repository-activity`

Which repositories are most active?

```json
{
  "data": [
    {
      "repository": "acme/backend",
      "runs": 142,
      "findings": 523,
      "avg_findings_per_run": 3.7,
      "critical_findings": 1,
      "last_run_at": "2026-01-31T10:30:00Z"
    },
    {
      "repository": "acme/frontend",
      "runs": 98,
      "findings": 234,
      "avg_findings_per_run": 2.4,
      "critical_findings": 0,
      "last_run_at": "2026-01-31T09:15:00Z"
    }
  ]
}
```

### Insights

- **High findings per run**: Might need more thorough PR review process
- **No recent runs**: Repository might be inactive or reviews disabled
- **Zero critical findings**: Good security hygiene

---

## Token Usage

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/token-usage`

Track AI resource consumption:

```json
{
  "data": {
    "period": {
      "start": "2026-01-01",
      "end": "2026-01-31"
    },
    "total": {
      "input_tokens": 2450000,
      "output_tokens": 380000,
      "estimated_cost_usd": 45.23
    },
    "by_provider": {
      "anthropic": {
        "input_tokens": 2100000,
        "output_tokens": 320000,
        "runs": 285
      },
      "openai": {
        "input_tokens": 350000,
        "output_tokens": 60000,
        "runs": 57
      }
    },
    "daily_average": {
      "input_tokens": 79032,
      "output_tokens": 12258
    }
  }
}
```

### Cost Estimation

Sentinel estimates AI costs based on published pricing:

| Provider | Input | Output |
|----------|-------|--------|
| Anthropic Claude 3.5 Sonnet | $3/1M tokens | $15/1M tokens |
| OpenAI GPT-4o | $5/1M tokens | $15/1M tokens |

**Note:** These are estimates. Actual costs depend on your provider agreements.

---

## Resolution Rate

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/resolution-rate`

Are findings being addressed?

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                     RESOLUTION TRACKING                                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  How Resolution is Determined:                                                  │
│  ├─ A finding is considered "resolved" if:                                     │
│  │   • The specific code issue is no longer present in a subsequent review     │
│  │   • The file/lines were modified in the PR that was merged                  │
│  │                                                                              │
│  └─ Resolution rate = (Resolved findings / Total findings) × 100               │
│                                                                                  │
│  Current Period:                                                                │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  Total Findings:     1247                                               │    │
│  │  Resolved:           1089 (87.3%)                                       │    │
│  │  Pending:            158  (12.7%)                                       │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
│  By Severity:                                                                   │
│  ├─ Critical: 100% resolved (3/3)                                              │
│  ├─ High:     95.6% resolved (43/45)                                           │
│  ├─ Medium:   89.2% resolved (347/389)                                         │
│  └─ Low:      86.0% resolved (696/810)                                         │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Success Rate

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/success-rate`

How often do reviews complete successfully?

```json
{
  "data": {
    "total_runs": 342,
    "completed": 298,
    "failed": 12,
    "skipped": 32,
    "success_rate": 87.13,
    "skip_reasons": {
      "no_provider_keys": 18,
      "config_error": 8,
      "trigger_rules": 6
    },
    "failure_reasons": {
      "timeout": 5,
      "api_error": 4,
      "rate_limit": 3
    }
  }
}
```

### Improving Success Rate

| Issue | Solution |
|-------|----------|
| High skip rate (no_provider_keys) | Add BYOK API keys |
| High skip rate (config_error) | Fix .sentinel/config.yaml |
| High failure rate (timeout) | Reduce PR size or context |
| High failure rate (rate_limit) | Upgrade AI provider plan |

---

## Run Activity Timeline

**Endpoint:** `GET /api/v1/workspaces/{workspace}/analytics/run-activity-timeline`

When are reviews happening?

```
    Runs by Day of Week

Mon ████████████████████████████████ 68
Tue ██████████████████████████████████████ 82
Wed ████████████████████████████████████████████ 95
Thu ██████████████████████████████████████████ 89
Fri ████████████████████████████ 58
Sat ███ 8
Sun ██ 5

    Runs by Hour (UTC)

    Peak hours: 14:00-17:00 UTC
    Low activity: 02:00-06:00 UTC
```

### Insights

- **Weekday heavy**: Team works standard schedule
- **Wednesday peak**: Mid-week productivity boost
- **Friday dip**: Fewer PRs opened before weekend

---

## Query Parameters

All analytics endpoints support common parameters:

| Parameter | Description | Example |
|-----------|-------------|---------|
| `start_date` | Period start (ISO 8601) | `2026-01-01` |
| `end_date` | Period end (ISO 8601) | `2026-01-31` |
| `repository_id` | Filter to specific repo | `123` |
| `granularity` | Aggregation level | `day`, `week`, `month` |

### Example Request

```bash
GET /api/v1/workspaces/1/analytics/quality-score-trend
    ?start_date=2026-01-01
    &end_date=2026-01-31
    &granularity=week
```

---

## Code Locations

| Component | Location |
|-----------|----------|
| Overview Metrics Action | `app/Actions/Analytics/GetOverviewMetrics.php` |
| Findings Distribution Action | `app/Actions/Analytics/GetFindingsDistribution.php` |
| Developer Leaderboard Action | `app/Actions/Analytics/GetDeveloperLeaderboard.php` |
| Review Velocity Action | `app/Actions/Analytics/GetReviewVelocity.php` |
| Token Usage Action | `app/Actions/Analytics/GetTokenUsage.php` |
| Quality Score Trend Action | `app/Actions/Analytics/GetQualityScoreTrend.php` |
| Controllers | `app/Http/Controllers/Analytics/*.php` |

---

## Best Practices

1. **Focus on trends, not absolutes** - A score of 75 improving to 80 matters more than the number itself
2. **Use for team health, not performance reviews** - Analytics should inform, not judge
3. **Compare to yourself, not others** - Track your team's progress over time
4. **Investigate anomalies** - Sudden changes often reveal real issues
5. **Share wins** - Use positive trends to celebrate team achievements

---

*Next: [Configuration System](./07-CONFIGURATION-SYSTEM.md) - Repository-level customization*
