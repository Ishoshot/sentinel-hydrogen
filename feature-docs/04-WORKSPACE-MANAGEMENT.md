# Workspace Management

## Your Organization in Sentinel

A **Workspace** is your organization's home in Sentinel. It's the container that holds everything—your team members, repositories, configurations, analytics, and billing. Think of it like a company's account on any SaaS platform, but with some important design decisions that make it particularly suitable for engineering teams.

---

## The Workspace Model

### Core Concept

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          WORKSPACE HIERARCHY                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌─────────────────────────────────────────────────────────────────────┐        │
│  │                         WORKSPACE                                    │        │
│  │                    "Acme Engineering"                                │        │
│  │                                                                      │        │
│  │  Owner: alice@acme.com                                              │        │
│  │  Plan: Orchestrate ($50/mo)                                         │        │
│  │  Created: Jan 2025                                                  │        │
│  └─────────────────────────────────────────────────────────────────────┘        │
│                                     │                                            │
│              ┌──────────────────────┼──────────────────────┐                    │
│              │                      │                      │                    │
│              ▼                      ▼                      ▼                    │
│     ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────────┐        │
│     │      TEAM       │   │   CONNECTIONS   │   │    SUBSCRIPTION     │        │
│     │                 │   │                 │   │                     │        │
│     │  5 Members      │   │  GitHub         │   │  Plan: Orchestrate  │        │
│     │  1 Owner        │   │  2 Installations│   │  Status: Active     │        │
│     │  2 Admins       │   │                 │   │  Renewal: Feb 1     │        │
│     │  2 Members      │   │                 │   │                     │        │
│     └─────────────────┘   └─────────────────┘   └─────────────────────┘        │
│                                     │                                            │
│                                     ▼                                            │
│                        ┌─────────────────────────────────────┐                  │
│                        │           REPOSITORIES              │                  │
│                        │                                     │                  │
│                        │  • acme/backend (enabled)          │                  │
│                        │  • acme/frontend (enabled)         │                  │
│                        │  • acme/mobile-app (disabled)      │                  │
│                        │  • acme/infrastructure (enabled)   │                  │
│                        └─────────────────────────────────────┘                  │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Why Workspaces Matter

Every piece of data in Sentinel is scoped to a Workspace:
- **Runs** belong to a Workspace
- **Findings** belong to a Workspace
- **Briefings** belong to a Workspace
- **Activities** belong to a Workspace
- **Provider Keys** belong to a Workspace

This isn't just organizational—it's a **security boundary**. Data from one Workspace can never leak to another.

---

## Teams and Members

Each Workspace has exactly **one Team**. The Team is the membership container that defines who has access.

### Member Roles

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          WORKSPACE ROLES                                         │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  👑 OWNER                                                                       │
│  ├─ Full control over everything                                                │
│  ├─ Manage billing and subscription                                             │
│  ├─ Delete workspace (irreversible)                                             │
│  ├─ Transfer ownership                                                          │
│  └─ All Admin permissions                                                       │
│                                                                                  │
│  🔧 ADMIN                                                                       │
│  ├─ Manage team members (invite, remove, change roles)                          │
│  ├─ Manage integrations and connections                                         │
│  ├─ Configure repository settings                                               │
│  ├─ Manage provider keys (BYOK)                                                │
│  ├─ View all analytics and briefings                                           │
│  └─ All Member permissions                                                      │
│                                                                                  │
│  👤 MEMBER                                                                      │
│  ├─ View runs and findings                                                      │
│  ├─ Trigger manual reviews                                                      │
│  ├─ Use @sentinel commands                                                     │
│  ├─ Generate briefings (if plan allows)                                        │
│  └─ View analytics for repositories they have access to                        │
│                                                                                  │
│  👁️ VIEWER (Coming Soon)                                                       │
│  ├─ Read-only access                                                            │
│  └─ View runs, findings, analytics                                              │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Role Permissions Matrix

| Permission | Owner | Admin | Member |
|------------|:-----:|:-----:|:------:|
| View dashboard | ✅ | ✅ | ✅ |
| View runs/findings | ✅ | ✅ | ✅ |
| Trigger manual reviews | ✅ | ✅ | ✅ |
| Use @sentinel commands | ✅ | ✅ | ✅ |
| Generate briefings | ✅ | ✅ | ✅ |
| Configure repo settings | ✅ | ✅ | ❌ |
| Manage provider keys | ✅ | ✅ | ❌ |
| Invite/remove members | ✅ | ✅ | ❌ |
| Change member roles | ✅ | ✅ | ❌ |
| Manage connections | ✅ | ✅ | ❌ |
| View/change billing | ✅ | ❌ | ❌ |
| Change subscription | ✅ | ❌ | ❌ |
| Delete workspace | ✅ | ❌ | ❌ |
| Transfer ownership | ✅ | ❌ | ❌ |

---

## Invitations

New members join through invitations:

### Invitation Flow

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          INVITATION FLOW                                         │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. ADMIN SENDS INVITE                                                          │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  POST /api/v1/workspaces/{workspace}/invitations                       │    │
│  │  {                                                                      │    │
│  │    "email": "bob@acme.com",                                            │    │
│  │    "role": "member"                                                    │    │
│  │  }                                                                      │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  2. INVITATION CREATED                                                          │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  • Unique token generated                                               │    │
│  │  • Expiration set (default: 7 days)                                    │    │
│  │  • Email notification sent to invitee                                  │    │
│  │  • Activity logged: "MemberInvited"                                    │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  3. INVITEE RECEIVES EMAIL                                                      │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  "You've been invited to join Acme Engineering on Sentinel"            │    │
│  │  [Accept Invitation] button → sentinel.app/invitations/{token}         │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                     │                                            │
│                                     ▼                                            │
│  4. INVITATION ACCEPTED                                                         │
│  ┌────────────────────────────────────────────────────────────────────────┐    │
│  │  • If user exists: Add to team with specified role                     │    │
│  │  • If new user: Create account, then add to team                       │    │
│  │  • Invitation marked as accepted                                       │    │
│  │  • Welcome notification sent                                           │    │
│  │  • Activity logged: "MemberJoined"                                     │    │
│  └────────────────────────────────────────────────────────────────────────┘    │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Invitation States

| State | Description |
|-------|-------------|
| **Pending** | Invitation sent, waiting for response |
| **Accepted** | User joined the workspace |
| **Expired** | Token expired (7 days default) |
| **Cancelled** | Admin cancelled the invitation |

### Invitation Management

```bash
# List pending invitations
GET /api/v1/workspaces/{workspace}/invitations

# Resend invitation email
POST /api/v1/workspaces/{workspace}/invitations/{id}/resend

# Cancel invitation
DELETE /api/v1/workspaces/{workspace}/invitations/{id}
```

---

## Multi-Workspace Support

Users can belong to multiple Workspaces:

### Use Cases

- **Consultant** working with multiple clients
- **Engineer** with personal projects and work projects
- **Manager** overseeing multiple teams/products

### Workspace Switching

The frontend provides workspace switching. The API uses the workspace ID in the URL:

```
/api/v1/workspaces/{workspace}/...
```

### Workspace Creation Rules

| Scenario | Allowed? |
|----------|----------|
| First workspace (any plan) | ✅ Yes |
| Additional workspace (all existing on paid plans) | ✅ Yes |
| Additional workspace (any existing on free plan) | ❌ No |

This ensures users are paying customers before creating multiple workspaces.

---

## Activity Tracking

Every significant action in a Workspace is logged:

### Activity Types

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          ACTIVITY TYPES                                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  WORKSPACE                          MEMBER                                      │
│  • workspace.created                • member.invited                            │
│  • workspace.updated                • member.joined                             │
│  • workspace.deleted                • member.removed                            │
│                                     • member.role_changed                       │
│                                                                                  │
│  REPOSITORY                         REVIEW                                      │
│  • repository.enabled               • run.completed                             │
│  • repository.disabled              • run.failed                                │
│  • repository.settings_updated      • run.skipped                               │
│                                                                                  │
│  BILLING                            INTEGRATION                                 │
│  • subscription.created             • connection.created                        │
│  • subscription.upgraded            • connection.removed                        │
│  • subscription.downgraded          • installation.added                        │
│  • subscription.cancelled           • installation.removed                      │
│                                                                                  │
│  BRIEFING                           PROVIDER KEY                                │
│  • briefing.generated               • provider_key.added                        │
│  • briefing.shared                  • provider_key.removed                      │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Activity Feed

```json
{
  "data": [
    {
      "id": 123,
      "type": "run.completed",
      "description": "Review completed for PR #456",
      "metadata": {
        "repository": "acme/backend",
        "pr_number": 456,
        "findings_count": 3
      },
      "created_at": "2026-01-15T10:30:00Z",
      "actor": {
        "id": 1,
        "name": "Alice Smith",
        "avatar_url": "..."
      }
    },
    ...
  ]
}
```

### Viewing Activities

```bash
GET /api/v1/workspaces/{workspace}/activities?limit=50
GET /api/v1/workspaces/{workspace}/activities?type=run.completed
GET /api/v1/workspaces/{workspace}/activities?since=2026-01-01
```

---

## Workspace Creation

### For New Users

When a user signs up with OAuth:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                    NEW USER ONBOARDING FLOW                                      │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  1. OAuth Sign In (GitHub/Google)                                               │
│              │                                                                   │
│              ▼                                                                   │
│  2. User account created                                                        │
│              │                                                                   │
│              ▼                                                                   │
│  3. Check for pending invitations                                               │
│              │                                                                   │
│      ┌───────┴───────┐                                                          │
│      │               │                                                          │
│      ▼               ▼                                                          │
│  Has Invite    No Invite                                                        │
│      │               │                                                          │
│      ▼               ▼                                                          │
│  Join existing   Create personal                                                │
│  workspace       workspace                                                       │
│      │               │                                                          │
│      └───────┬───────┘                                                          │
│              │                                                                   │
│              ▼                                                                   │
│  4. Welcome notification sent                                                   │
│              │                                                                   │
│              ▼                                                                   │
│  5. Redirect to dashboard                                                       │
│                                                                                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Manual Workspace Creation

Existing users can create additional workspaces:

```bash
POST /api/v1/workspaces
{
  "name": "My New Project"
}
```

---

## Data Isolation

### How It Works

Every query in Sentinel is scoped by `workspace_id`:

```php
// In models (example from Run.php)
public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
{
    return $query->where('workspace_id', $workspace->id);
}

// In middleware (EnsureWorkspaceAccess)
if (!$user->belongsToWorkspace($workspace)) {
    abort(403, 'You do not have access to this workspace.');
}

// Current workspace is set in the container
app()->instance('current_workspace', $workspace);
```

### Database-Level Enforcement

All tables with user data include `workspace_id`:
- `runs.workspace_id`
- `findings.workspace_id`
- `repositories.workspace_id`
- `provider_keys.workspace_id`
- `briefing_generations.workspace_id`
- `activities.workspace_id`
- etc.

Foreign key constraints ensure referential integrity.

---

## Workspace Settings

### General Settings

| Setting | Description | Who Can Change |
|---------|-------------|----------------|
| Name | Workspace display name | Owner, Admin |
| Slug | URL-friendly identifier | Owner, Admin |
| Avatar | Workspace icon | Owner, Admin |
| Timezone | Default timezone for reports | Owner, Admin |

### Notification Settings

| Setting | Description |
|---------|-------------|
| Email digest | Daily/weekly summary emails |
| Review notifications | Notify on completed reviews |
| Billing alerts | Notify before plan limits |

---

## Code Locations

| Component | Location |
|-----------|----------|
| Workspace Model | `app/Models/Workspace.php` |
| Team Model | `app/Models/Team.php` |
| TeamMember Model | `app/Models/TeamMember.php` |
| Invitation Model | `app/Models/Invitation.php` |
| Activity Model | `app/Models/Activity.php` |
| Create Workspace Action | `app/Actions/Workspaces/CreateWorkspace.php` |
| Create Invitation Action | `app/Actions/Invitations/CreateInvitation.php` |
| Accept Invitation Action | `app/Actions/Invitations/AcceptInvitation.php` |
| Log Activity Action | `app/Actions/Activities/LogActivity.php` |
| Workspace Policy | `app/Policies/WorkspacePolicy.php` |
| EnsureWorkspaceAccess Middleware | `app/Http/Middleware/EnsureWorkspaceAccess.php` |

---

## API Reference

### Workspaces

```bash
# List user's workspaces
GET /api/v1/workspaces

# Get workspace details
GET /api/v1/workspaces/{workspace}

# Create workspace
POST /api/v1/workspaces
{ "name": "My Workspace" }

# Update workspace
PATCH /api/v1/workspaces/{workspace}
{ "name": "New Name" }

# Delete workspace (Owner only)
DELETE /api/v1/workspaces/{workspace}
```

### Team Members

```bash
# List team members
GET /api/v1/workspaces/{workspace}/team-members

# Update member role
PATCH /api/v1/workspaces/{workspace}/team-members/{member}
{ "role": "admin" }

# Remove member
DELETE /api/v1/workspaces/{workspace}/team-members/{member}
```

### Invitations

```bash
# List pending invitations
GET /api/v1/workspaces/{workspace}/invitations

# Create invitation
POST /api/v1/workspaces/{workspace}/invitations
{ "email": "user@example.com", "role": "member" }

# Resend invitation
POST /api/v1/workspaces/{workspace}/invitations/{id}/resend

# Cancel invitation
DELETE /api/v1/workspaces/{workspace}/invitations/{id}

# Accept invitation (public endpoint)
POST /api/v1/invitations/{token}/accept
```

---

*Next: [GitHub Integration](./05-GITHUB-INTEGRATION.md) - Seamless source control connection*
