# Briefings

## Turning Data into Stories

Imagine trying to explain to your CEO what your engineering team accomplished last month. You could dump a spreadsheet with 500 rows of PR data, or you could tell them a story. Briefings are Sentinel's way of telling that story.

Briefings are **AI-powered narrative reports** that transform raw engineering metrics into compelling, human-readable summaries. They're not just charts and numbers—they're prose that explains what happened, why it matters, and what's coming next.

---

## What Makes Briefings Special?

### Traditional Reports vs. Briefings

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                     TRADITIONAL REPORT                                            │
├──────────────────────────────────────────────────────────────────────────────────┤
│                                                                                   │
│  Total PRs Merged: 127                                                           │
│  Average Review Time: 4.2 hours                                                  │
│  Finding Categories: Security (23), Performance (45), Maintainability (89)       │
│  Top Contributors: @alice (32), @bob (28), @carol (22)                           │
│                                                                                   │
│  [Bar Chart] [Line Graph] [Pie Chart]                                            │
│                                                                                   │
└──────────────────────────────────────────────────────────────────────────────────┘

                              vs.

┌──────────────────────────────────────────────────────────────────────────────────┐
│                           SENTINEL BRIEFING                                       │
├──────────────────────────────────────────────────────────────────────────────────┤
│                                                                                   │
│  "This was a transformative month for the platform team. With 127 pull           │
│   requests merged—a 23% increase from last month—the team demonstrated           │
│   exceptional velocity while maintaining code quality.                            │
│                                                                                   │
│   A notable achievement: Alice led a security hardening initiative that           │
│   addressed 23 potential vulnerabilities, reducing the team's security            │
│   debt by 40%. This proactive approach positions us well for the                  │
│   upcoming SOC 2 audit.                                                           │
│                                                                                   │
│   The focus on performance optimization paid off—N+1 query issues dropped         │
│   from 12 last month to just 3 this month. Bob's work on the caching layer        │
│   was instrumental in this improvement.                                           │
│                                                                                   │
│   Looking ahead: The team is 80% complete on the Q1 roadmap. With the             │
│   current velocity, we're on track for an early completion..."                    │
│                                                                                   │
│  🏆 ACHIEVEMENT UNLOCKED: "Security Champion" - Alice                             │
│  📈 STREAK: 14 days without critical findings                                     │
│                                                                                   │
└──────────────────────────────────────────────────────────────────────────────────┘
```

---

## Key Differentiators

| Feature | Description |
|---------|-------------|
| **Narrative Mode** | AI writes prose, not bullet points. Reads like a team update, not a data dump. |
| **Achievement Detection** | Gamification that celebrates wins and recognizes contributors |
| **Presentation Mode** | One-click transformation into slide decks for leadership meetings |
| **Smart Excerpts** | Pre-formatted snippets for Slack, email, LinkedIn—share with one click |
| **Real-time Progress** | WebSocket updates via Reverb show generation progress live |
| **External Sharing** | Token-secured public links for stakeholders outside your org |

---

## Briefing Types

Sentinel provides several pre-built briefing templates, each designed for a specific audience and purpose:

### Available Briefings

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          BRIEFING TEMPLATES                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  📊 DAILY STANDUP UPDATE                                                        │
│  ├─ Target: Developers, Engineering Managers                                    │
│  ├─ Requires AI: Yes                                                            │
│  └─ Perfect for: Morning standup prep, async status updates                     │
│                                                                                  │
│  📈 WEEKLY TEAM SUMMARY                                                         │
│  ├─ Target: Engineering Managers, CTOs                                          │
│  ├─ Requires AI: Yes                                                            │
│  └─ Perfect for: Weekly team meetings, leadership updates                       │
│                                                                                  │
│  🚀 DELIVERY VELOCITY REPORT                                                    │
│  ├─ Target: Engineering Managers, CTOs                                          │
│  ├─ Requires AI: No (data-driven)                                               │
│  └─ Perfect for: Sprint reviews, capacity planning                              │
│                                                                                  │
│  ⭐ ENGINEER SPOTLIGHT                                                          │
│  ├─ Target: Engineering Managers                                                │
│  ├─ Requires AI: Yes                                                            │
│  └─ Perfect for: 1:1 prep, performance reviews                                  │
│                                                                                  │
│  🏢 TEAM UPDATE FOR LEADERSHIP                                                  │
│  ├─ Target: CTOs, VP Engineering                                                │
│  ├─ Requires AI: Yes                                                            │
│  └─ Perfect for: Board meetings, executive summaries                            │
│                                                                                  │
│  🔄 SPRINT RETROSPECTIVE                                                        │
│  ├─ Target: Engineering Managers, Scrum Masters                                 │
│  ├─ Requires AI: Yes                                                            │
│  └─ Perfect for: Sprint retros, process improvement                             │
│                                                                                  │
│  💚 CODE HEALTH REPORT                                                          │
│  ├─ Target: Tech Leads, Architects                                              │
│  ├─ Requires AI: No (metrics-driven)                                            │
│  └─ Perfect for: Technical debt tracking, quality gates                         │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## How Briefings Work

### Generation Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        BRIEFING GENERATION FLOW                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  USER ACTION                                                                     │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  "Generate Weekly Team Summary for Jan 15-21"                           │    │
│  │                                                                          │    │
│  │  Parameters:                                                             │    │
│  │  • Date range: 2026-01-15 to 2026-01-21                                 │    │
│  │  • Repositories: All (or specific selection)                            │    │
│  │  • Focus areas: Default                                                 │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  VALIDATION & QUEUING                                                           │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  ✓ Check plan eligibility (does plan allow this briefing?)              │    │
│  │  ✓ Validate parameters (date range within limits, repos exist)         │    │
│  │  ✓ Check concurrent generation limit                                    │    │
│  │  ✓ Create BriefingGeneration record (status: pending)                  │    │
│  │  ✓ Dispatch ProcessBriefingGeneration job                              │    │
│  │  ✓ Return generation ID immediately                                     │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                          WebSocket: "briefing.started"                          │
│                                     │                                            │
│                                     ▼                                            │
│  DATA COLLECTION (0-30%)                                                        │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  📊 Gathering metrics...                                                │    │
│  │                                                                          │    │
│  │  • Query runs table for date range                                       │    │
│  │  • Aggregate findings by category and severity                          │    │
│  │  • Calculate review velocity metrics                                    │    │
│  │  • Identify top contributors                                            │    │
│  │  • Detect trends vs. previous period                                    │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                        WebSocket: "briefing.progress" (30%)                     │
│                                     │                                            │
│                                     ▼                                            │
│  ACHIEVEMENT DETECTION (30-40%)                                                 │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  🏆 Detecting achievements...                                           │    │
│  │                                                                          │    │
│  │  • Milestones: "100th PR merged", "1000 findings resolved"              │    │
│  │  • Streaks: "14 days without critical findings"                         │    │
│  │  • Personal Bests: "Alice's most productive week"                       │    │
│  │  • Team Records: "Fastest average review time"                          │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                        WebSocket: "briefing.progress" (40%)                     │
│                                     │                                            │
│                                     ▼                                            │
│  NARRATIVE GENERATION (40-80%)                                                  │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  🤖 Generating narrative...                                             │    │
│  │                                                                          │    │
│  │  • Build prompt with collected data                                      │    │
│  │  • Include briefing template instructions                               │    │
│  │  • Send to AI provider (Claude/GPT-4)                                   │    │
│  │  • Parse structured response                                            │    │
│  │  • Extract narrative sections                                           │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                        WebSocket: "briefing.progress" (80%)                     │
│                                     │                                            │
│                                     ▼                                            │
│  SMART EXCERPTS (80-90%)                                                        │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  📝 Generating excerpts...                                              │    │
│  │                                                                          │    │
│  │  • Slack-optimized summary (280 chars)                                  │    │
│  │  • Email digest (500 chars)                                             │    │
│  │  • LinkedIn-ready achievement (200 chars)                               │    │
│  │  • Full markdown export                                                 │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                        WebSocket: "briefing.progress" (90%)                     │
│                                     │                                            │
│                                     ▼                                            │
│  OUTPUT RENDERING (90-100%)                                                     │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  🎨 Rendering outputs...                                                │    │
│  │                                                                          │    │
│  │  • Generate HTML version                                                 │    │
│  │  • Render PDF via Browsershot                                           │    │
│  │  • Build slide deck                                                     │    │
│  │  • Store files to R2 storage                                            │    │
│  │  • Update generation record                                             │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                        WebSocket: "briefing.completed"                          │
│                                     │                                            │
│                                     ▼                                            │
│  READY FOR VIEWING                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  ✅ Briefing ready!                                                     │    │
│  │                                                                          │    │
│  │  Available formats:                                                      │    │
│  │  • View in dashboard (HTML)                                             │    │
│  │  • Download PDF                                                         │    │
│  │  • Export Markdown                                                      │    │
│  │  • Open Slides                                                          │    │
│  │  • Share externally                                                     │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Briefing Subscriptions

Don't want to remember to generate briefings? Set up automatic scheduling:

### Schedule Presets

| Preset | When It Runs | Ideal For |
|--------|--------------|-----------|
| **Daily** | Every day at 8 AM UTC | Standup prep |
| **Weekly** | Monday at 9 AM UTC | Weekly team meetings |
| **Monthly** | 1st of month at 9 AM UTC | Leadership updates |

### Delivery Channels

When a scheduled briefing completes, it can be delivered via:

- **Push Notification** - In-app notification in Sentinel dashboard
- **Email** - HTML email with summary and download links
- **Slack** - Post to a channel via webhook (coming soon)

### Creating a Subscription

```
POST /api/v1/workspaces/{workspace}/briefing-subscriptions
{
  "briefing_id": 2,
  "schedule_preset": "weekly",
  "schedule_day": 1,  // Monday
  "schedule_hour": 9,
  "parameters": {
    "repositories": ["all"]
  },
  "delivery_channels": ["email", "push"]
}
```

---

## External Sharing

Need to share a briefing with someone outside your organization? Create a share link:

### Share Link Features

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          SHARE LINK OPTIONS                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  🔗 TOKEN-SECURED                                                               │
│  └─ 64-character cryptographically random token                                 │
│     Example: https://sentinel.app/briefings/share/abc123...xyz789               │
│                                                                                  │
│  ⏰ EXPIRATION                                                                   │
│  └─ Set how long the link is valid (default: 7 days)                            │
│                                                                                  │
│  🔒 PASSWORD PROTECTION (Optional)                                              │
│  └─ Require a password to view the briefing                                     │
│                                                                                  │
│  📊 ACCESS LIMITS (Optional)                                                    │
│  └─ Maximum number of times the link can be accessed                            │
│                                                                                  │
│  📈 ACCESS TRACKING                                                             │
│  └─ See who viewed and when                                                     │
│                                                                                  │
│  🚫 INSTANT REVOCATION                                                          │
│  └─ Disable the link anytime                                                    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Creating a Share Link

```
POST /api/v1/workspaces/{workspace}/briefing-generations/{id}/share
{
  "expires_in_days": 7,
  "password": "optional-password",
  "max_accesses": null  // unlimited
}
```

Response:
```json
{
  "data": {
    "id": 123,
    "url": "https://sentinel.app/briefings/share/abc123...xyz789",
    "expires_at": "2026-02-07T00:00:00Z",
    "is_password_protected": true
  }
}
```

---

## Plan Integration

Briefing access is controlled by your subscription plan:

| Plan | Briefings Available | Generations/Month | Scheduling | External Sharing |
|------|---------------------|-------------------|------------|------------------|
| **Free** | 2 basic | 5 | No | No |
| **Team** | All standard | 50 | Yes | No |
| **Business** | All | Unlimited | Yes | Yes |
| **Enterprise** | All + Custom | Unlimited | Yes | Yes |

---

## Data Model

### Core Entities

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          BRIEFINGS DATA MODEL                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  BRIEFING (Template)                                                            │
│  ┌─────────────────────────────────────────────────────────────────────┐       │
│  │  id, workspace_id, title, slug, description                          │       │
│  │  icon, target_roles, parameter_schema, prompt_path                   │       │
│  │  requires_ai, eligible_plan_ids, output_formats                      │       │
│  │  is_schedulable, is_system, sort_order, is_active                   │       │
│  └─────────────────────────────────────────────────────────────────────┘       │
│                           │                                                      │
│                           │ 1:N                                                  │
│                           ▼                                                      │
│  BRIEFING_GENERATION (Instance)                                                 │
│  ┌─────────────────────────────────────────────────────────────────────┐       │
│  │  id, workspace_id, briefing_id, generated_by_id                      │       │
│  │  parameters (JSONB), status, progress, progress_message             │       │
│  │  started_at, completed_at, narrative (TEXT)                         │       │
│  │  structured_data (JSONB), achievements (JSONB)                       │       │
│  │  excerpts (JSONB), output_paths (JSONB), metadata (JSONB)           │       │
│  │  error_message, expires_at                                          │       │
│  └─────────────────────────────────────────────────────────────────────┘       │
│                           │                                                      │
│              ┌────────────┼────────────┐                                        │
│              │            │            │                                        │
│              ▼            ▼            ▼                                        │
│  BRIEFING_SHARE    BRIEFING_DOWNLOAD   BRIEFING_SUBSCRIPTION                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐                  │
│  │ token        │  │ format       │  │ schedule_preset      │                  │
│  │ password_hash│  │ source       │  │ schedule_day/hour    │                  │
│  │ expires_at   │  │ ip_address   │  │ delivery_channels    │                  │
│  │ access_count │  │ user_agent   │  │ next_scheduled_at    │                  │
│  └──────────────┘  └──────────────┘  └──────────────────────┘                  │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Real-Time Updates

Briefing generation uses WebSocket broadcasting for real-time progress updates:

### Channel

```
private-workspace.{workspace_id}.briefings
```

### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `briefing.started` | generation_id, briefing_id, status | Generation has begun |
| `briefing.progress` | generation_id, progress (0-100), message | Progress update |
| `briefing.completed` | generation_id, briefing_slug | Ready to view |
| `briefing.failed` | generation_id, error | Generation failed |

### Frontend Integration

```javascript
// Subscribe to briefing updates
Echo.private(`workspace.${workspaceId}.briefings`)
  .listen('briefing.progress', (e) => {
    updateProgressBar(e.generation_id, e.progress);
    updateStatusMessage(e.message);
  })
  .listen('briefing.completed', (e) => {
    showCompletionNotification(e.generation_id);
    navigateToBriefing(e.briefing_slug);
  });
```

---

## Code Locations

| Component | Location |
|-----------|----------|
| Generate Briefing Action | `app/Actions/Briefings/GenerateBriefing.php` |
| Processing Job | `app/Jobs/Briefings/ProcessBriefingGeneration.php` |
| Data Collector Service | `app/Services/Briefings/BriefingDataCollectorService.php` |
| Narrative Generator | `app/Services/Briefings/NarrativeGeneratorService.php` |
| Slides Builder | `app/Services/Briefings/BriefingSlidesBuilderService.php` |
| Events | `app/Events/Briefings/*.php` |
| Models | `app/Models/Briefing*.php` |
| API Controllers | `app/Http/Controllers/Briefings/*.php` |

---

## Best Practices

1. **Start with Weekly Summaries** - They provide the best balance of detail and frequency
2. **Include All Repositories** - Cross-repo insights are often the most valuable
3. **Set Up Subscriptions** - Automate what you can; manual generation is for ad-hoc needs
4. **Share Thoughtfully** - Use password protection for sensitive data
5. **Track Downloads** - Understand how stakeholders consume your briefings

---

*Next: [@sentinel Commands](./03-SENTINEL-COMMANDS.md) - Interactive AI assistance in PRs*
