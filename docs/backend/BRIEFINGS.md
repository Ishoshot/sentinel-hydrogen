# Sentinel – Briefings

This document defines the Briefings feature for Sentinel.
It describes the data model, architecture, APIs, and implementation details.

All implementation MUST conform to this document.

---

## Overview

**Briefings** are AI-powered, narrative-driven reports that transform raw engineering data
into compelling stories. Unlike traditional reports that present data as tables and charts,
Briefings tell the story of what happened, why it matters, and what's ahead.

Key differentiators:

- **Narrative Mode**: AI writes prose, not bullet points
- **Achievement Detection**: Gamification that celebrates wins
- **Presentation Mode**: One-click slide transformation
- **Smart Excerpts**: Pre-formatted content for Slack, email, LinkedIn
- **Real-time Progress**: WebSocket updates via Reverb
- **External Sharing**: Token-secured public links

---

## Domain Vocabulary

These terms are additions to the Sentinel glossary.

### Briefing

A template that defines a type of report — its parameters, prompts, eligible plans, and target audience.

Briefings may be:

- **System**: Provided by Sentinel, available to all Workspaces
- **Custom**: Created by a Workspace (future capability)

---

### Briefing Generation

A specific instance of a Briefing produced with concrete parameters.

A Briefing Generation:

- Is created when a user requests a Briefing with specific parameters
- Is processed asynchronously in the background
- Is immutable once completed
- Contains both narrative content and structured data

---

### Briefing Subscription

A recurring schedule for automatic Briefing generation with pre-set parameters.

Subscriptions support:

- Schedule presets: daily, weekly, monthly
- Delivery channels: push notification, email, Slack
- Automatic parameter defaults

---

### Briefing Share

A token-secured external link allowing non-members to view a specific Briefing Generation.

Shares support:

- Expiration dates
- Optional password protection
- Access tracking
- Revocation

---

### Achievement

A milestone or recognition surfaced within a Briefing narrative.

Achievement types:

- **Milestone**: Team-level accomplishments (e.g., "100 PRs merged")
- **Streak**: Consecutive day records (e.g., "14 days incident-free")
- **Personal Best**: Individual contributor records

---

## Data Model

### briefings

Represents Briefing templates.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| workspace_id | bigint nullable | FK to workspaces (null = system template) |
| title | varchar(255) | Display title |
| slug | varchar(100) | URL-safe identifier (unique) |
| description | text | User-facing description |
| icon | varchar(50) | Icon identifier for UI |
| target_roles | jsonb | Array of target job roles |
| parameter_schema | jsonb | JSON Schema defining accepted parameters |
| prompt_path | varchar(255) | Blade template path for AI prompt |
| requires_ai | boolean | Whether this briefing uses AI generation |
| eligible_plan_ids | jsonb | Array of plan IDs that can access this briefing |
| estimated_duration_seconds | integer | Expected generation time for UX |
| output_formats | jsonb | Supported formats (html, pdf, markdown, slides) |
| is_schedulable | boolean | Whether subscriptions are allowed |
| is_system | boolean | System template vs workspace-custom |
| sort_order | integer | Display ordering |
| is_active | boolean | Soft enable/disable |
| created_at | timestamp | |
| updated_at | timestamp | |

Indexes:

- `workspace_id, is_active`
- `slug` (unique)
- `is_system, is_active`

---

### briefing_generations

Represents generated Briefing instances.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| workspace_id | bigint | FK to workspaces |
| briefing_id | bigint | FK to briefings |
| generated_by_id | bigint | FK to users |
| parameters | jsonb | Actual parameter values used |
| status | varchar(50) | pending, processing, completed, failed |
| progress | integer | 0-100 progress percentage |
| progress_message | varchar(255) | Current step description |
| started_at | timestamp | When processing began |
| completed_at | timestamp | When processing finished |
| narrative | text | AI-generated story content |
| structured_data | jsonb | Raw data for visualizations/slides |
| achievements | jsonb | Detected achievements |
| excerpts | jsonb | Pre-formatted smart excerpts |
| output_paths | jsonb | Paths to generated files by format |
| metadata | jsonb | Token usage, processing stats |
| error_message | text | Failure reason if applicable |
| expires_at | timestamp nullable | Auto-cleanup date |
| created_at | timestamp | |

Indexes:

- `workspace_id, created_at DESC`
- `workspace_id, briefing_id, created_at DESC`
- `workspace_id, generated_by_id`
- `status`
- `expires_at` (for cleanup jobs)

---

### briefing_downloads

Tracks download/access events for analytics.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| briefing_generation_id | bigint | FK to briefing_generations |
| workspace_id | bigint | FK to workspaces |
| user_id | bigint nullable | FK to users (null for external shares) |
| format | varchar(20) | html, pdf, markdown, slides |
| source | varchar(50) | dashboard, share_link, api, email |
| ip_address | inet | Client IP |
| user_agent | text | Client user agent |
| downloaded_at | timestamp | |

Indexes:

- `briefing_generation_id, downloaded_at`
- `workspace_id, downloaded_at`
- `user_id, downloaded_at` (where not null)

---

### briefing_subscriptions

Represents scheduled recurring Briefing generations.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| workspace_id | bigint | FK to workspaces |
| user_id | bigint | FK to users (subscriber) |
| briefing_id | bigint | FK to briefings |
| schedule_preset | varchar(50) | daily, weekly, monthly |
| schedule_day | integer nullable | 1-7 for weekly, 1-28 for monthly |
| schedule_hour | integer | 0-23 UTC |
| parameters | jsonb | Default params for scheduled runs |
| delivery_channels | jsonb | Array of channels (push, email, slack) |
| slack_webhook_url | text nullable | Encrypted webhook URL |
| last_generated_at | timestamp nullable | |
| next_scheduled_at | timestamp | |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

Indexes:

- `workspace_id, is_active`
- `next_scheduled_at, is_active` (for scheduler)
- `user_id, is_active`

---

### briefing_shares

Represents external share links.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| briefing_generation_id | bigint | FK to briefing_generations |
| workspace_id | bigint | FK to workspaces |
| created_by_id | bigint | FK to users |
| token | varchar(64) | Unique share token |
| password_hash | varchar(255) nullable | Optional password |
| access_count | integer | Number of views |
| max_accesses | integer nullable | Access limit (null = unlimited) |
| expires_at | timestamp | Expiration date |
| is_active | boolean | Can be revoked |
| created_at | timestamp | |

Indexes:

- `token` (unique)
- `briefing_generation_id`
- `expires_at, is_active` (for cleanup)

---

## Configuration

### config/briefings.php

```php
return [
    'limits' => [
        'max_date_range_days' => env('BRIEFINGS_MAX_DATE_RANGE', 90),
        'max_repositories' => env('BRIEFINGS_MAX_REPOSITORIES', 10),
        'max_concurrent_generations' => env('BRIEFINGS_MAX_CONCURRENT', 3),
        'generation_timeout_seconds' => env('BRIEFINGS_TIMEOUT', 300),
    ],

    'retention' => [
        'generations_days' => env('BRIEFINGS_RETENTION_DAYS', 90),
        'shares_default_expiry_days' => env('BRIEFINGS_SHARE_EXPIRY', 7),
    ],

    'storage' => [
        'disk' => env('BRIEFINGS_DISK', 'r2'),
        'path' => 'briefings',
    ],

    'scheduling' => [
        'presets' => [
            'daily' => ['hour' => 8],
            'weekly' => ['day' => 1, 'hour' => 9],
            'monthly' => ['day' => 1, 'hour' => 9],
        ],
    ],

    'pdf' => [
        'driver' => env('BRIEFINGS_PDF_DRIVER', 'browsershot'),
    ],
];
```

---

## Storage

Briefings use Cloudflare R2 for storing generated files (PDF, HTML exports).

Configuration:

- Disk: `r2` (configured via Laravel Cloud)
- Path pattern: `briefings/{workspace_id}/{generation_id}/{format}.{ext}`
- Access: Private with temporary URLs for downloads

---

## Real-Time Updates (Reverb)

Briefing generation progress is broadcast via Laravel Reverb.

### Channel

```
private-workspace.{workspace_id}.briefings
```

### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `briefing.progress` | generation_id, progress, message | Progress update |
| `briefing.completed` | generation_id, briefing_slug | Generation finished |
| `briefing.failed` | generation_id, error | Generation failed |

### Channel Authorization

```php
// routes/channels.php
Broadcast::channel('workspace.{workspaceId}.briefings', function (User $user, int $workspaceId) {
    return $user->belongsToWorkspace($workspaceId);
});
```

---

## API Endpoints

### Workspace Endpoints (User-facing)

All endpoints are prefixed with `/api/v1/workspaces/{workspace}`.

#### Briefings (Templates)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/briefings` | List available briefings for workspace's plan |
| GET | `/briefings/{slug}` | Get briefing details and parameter schema |

#### Briefing Generations

| Method | Path | Description |
|--------|------|-------------|
| POST | `/briefings/{slug}/generate` | Request new generation |
| GET | `/briefing-generations` | List workspace's generations |
| GET | `/briefing-generations/{id}` | Get generation details |
| GET | `/briefing-generations/{id}/download/{format}` | Download in format |

#### Briefing Subscriptions

| Method | Path | Description |
|--------|------|-------------|
| GET | `/briefing-subscriptions` | List user's subscriptions |
| POST | `/briefing-subscriptions` | Create subscription |
| PATCH | `/briefing-subscriptions/{id}` | Update subscription |
| DELETE | `/briefing-subscriptions/{id}` | Cancel subscription |

#### Briefing Shares

| Method | Path | Description |
|--------|------|-------------|
| POST | `/briefing-generations/{id}/share` | Create share link |
| DELETE | `/briefing-shares/{id}` | Revoke share |

### Public Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/briefings/share/{token}` | View shared briefing |

### Admin Endpoints

All endpoints are prefixed with `/admin`.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/briefings` | List all briefings |
| POST | `/briefings` | Create briefing template |
| GET | `/briefings/{id}` | Get briefing |
| PATCH | `/briefings/{id}` | Update briefing |
| DELETE | `/briefings/{id}` | Delete briefing |

---

## Plan Integration

Briefings access is controlled by plan features.

### Plan Features (JSONB)

```json
{
    "briefings": {
        "enabled": true,
        "allowed_briefing_ids": [1, 2, 3],
        "generations_per_month": 10,
        "scheduling_enabled": false,
        "external_sharing_enabled": false
    }
}
```

### Plan Tiers

| Plan | Briefings | Generations/Month | Scheduling | Sharing |
|------|-----------|-------------------|------------|---------|
| Free | 2 basic | 5 | No | No |
| Team | All standard | 50 | Yes | No |
| Business | All | Unlimited | Yes | Yes |
| Enterprise | All + Custom | Unlimited | Yes | Yes |

---

## Architecture

### Directory Structure

```
app/
├── Actions/
│   └── Briefings/
│       ├── GenerateBriefing.php
│       ├── CreateBriefingSubscription.php
│       ├── UpdateBriefingSubscription.php
│       ├── CancelBriefingSubscription.php
│       ├── ShareBriefingGeneration.php
│       ├── RevokeBriefingShare.php
│       └── TrackBriefingDownload.php
│
├── Events/
│   └── Briefings/
│       ├── BriefingGenerationStarted.php
│       ├── BriefingGenerationProgress.php
│       ├── BriefingGenerationCompleted.php
│       └── BriefingGenerationFailed.php
│
├── Jobs/
│   └── Briefings/
│       ├── ProcessBriefingGeneration.php
│       ├── GenerateScheduledBriefings.php
│       ├── RenderBriefingPdf.php
│       └── CleanupExpiredBriefings.php
│
├── Services/
│   └── Briefings/
│       ├── Contracts/
│       │   ├── BriefingDataCollector.php
│       │   └── BriefingNarrativeGenerator.php
│       ├── DataCollectors/
│       │   ├── StandupDataCollector.php
│       │   ├── DeliveryVelocityDataCollector.php
│       │   └── TeamUpdateDataCollector.php
│       ├── BriefingParameterValidator.php
│       ├── BriefingLimitEnforcer.php
│       ├── AchievementDetector.php
│       └── NarrativeGenerator.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Briefings/
│   │   │   ├── BriefingController.php
│   │   │   ├── BriefingGenerationController.php
│   │   │   ├── BriefingSubscriptionController.php
│   │   │   ├── BriefingShareController.php
│   │   │   └── PublicBriefingController.php
│   │   └── Admin/
│   │       └── BriefingController.php
│   ├── Requests/
│   │   └── Briefings/
│   │       ├── GenerateBriefingRequest.php
│   │       ├── CreateSubscriptionRequest.php
│   │       └── CreateShareRequest.php
│   └── Resources/
│       └── Briefings/
│           ├── BriefingResource.php
│           ├── BriefingGenerationResource.php
│           └── BriefingSubscriptionResource.php
│
├── Models/
│   ├── Briefing.php
│   ├── BriefingGeneration.php
│   ├── BriefingDownload.php
│   ├── BriefingSubscription.php
│   └── BriefingShare.php
│
└── Enums/
    ├── BriefingGenerationStatus.php
    ├── BriefingSchedulePreset.php
    ├── BriefingOutputFormat.php
    └── BriefingDeliveryChannel.php
```

---

## Execution Flow

### Manual Generation

1. User requests briefing via API with parameters
2. Controller validates request via Form Request
3. GenerateBriefing Action:
   - Validates plan eligibility
   - Validates parameters against schema
   - Creates BriefingGeneration record (status: pending)
   - Dispatches ProcessBriefingGeneration job
   - Returns generation ID immediately
4. ProcessBriefingGeneration Job:
   - Updates status to processing
   - Broadcasts `BriefingGenerationStarted` event
   - Collects data via appropriate DataCollector
   - Detects achievements
   - Generates narrative via AI (if required)
   - Generates smart excerpts
   - Broadcasts progress via `BriefingGenerationProgress`
   - Renders output formats (HTML, PDF)
   - Stores files to R2
   - Updates generation record
   - Broadcasts `BriefingGenerationCompleted`
5. Frontend receives WebSocket event and displays result

### Scheduled Generation

1. GenerateScheduledBriefings job runs via scheduler
2. Queries subscriptions where `next_scheduled_at <= now()`
3. For each subscription:
   - Creates BriefingGeneration with subscription parameters
   - Dispatches ProcessBriefingGeneration job
   - Updates subscription's next_scheduled_at
   - Sends delivery via configured channels

---

## Seed Briefing Templates

Initial system briefings:

| Slug | Title | Target Roles | Requires AI |
|------|-------|--------------|-------------|
| `standup-update` | Daily Standup Update | developer, engineering_manager | Yes |
| `weekly-team-summary` | Weekly Team Summary | engineering_manager, cto | Yes |
| `delivery-velocity` | Delivery Velocity Report | engineering_manager, cto | No |
| `engineer-spotlight` | Engineer Spotlight | engineering_manager | Yes |
| `company-update` | Team Update for Leadership | cto, vp_engineering | Yes |
| `sprint-retrospective` | Sprint Retrospective | engineering_manager, scrum_master | Yes |
| `code-health` | Code Health Report | tech_lead, architect | No |

---

## Deferred to v2

The following features are documented but not implemented in v1:

- Voice narration (TTS audio generation)
- Custom workspace-defined briefing templates
- Benchmark comparisons across anonymized teams
- Report diffs (compare two generations side-by-side)
- Collaborative annotations
- Webhook delivery channel

---

## Security Considerations

- All workspace data access is scoped by workspace_id
- Share tokens are cryptographically random (64 characters)
- Password-protected shares use bcrypt hashing
- Temporary URLs for R2 files expire after 1 hour
- External shares can be revoked instantly
- Rate limiting on generation requests

---

## Testing Requirements

All Briefing functionality must have comprehensive tests:

- Unit tests for data collectors, validators, achievement detection
- Feature tests for all API endpoints
- Feature tests for admin endpoints
- Integration tests for generation workflow
- Tests for plan eligibility enforcement
- Tests for real-time event broadcasting

---

This document defines the Briefings feature for Sentinel.
Implementation details follow this specification.
