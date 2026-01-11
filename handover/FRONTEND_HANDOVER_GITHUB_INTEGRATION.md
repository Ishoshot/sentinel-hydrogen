# Frontend Implementation: GitHub Integration (Phase 2)

> **This is a handover document for the frontend agent. Delete this file after use.**

## Context

The backend for Sentinel (Laravel 12 API) has implemented **GitHub Integration** (Phase 2). You need to implement the corresponding frontend in **Vue/Nuxt 4**.

This feature covers:
- Connecting a Workspace to GitHub via the GitHub App
- Managing the GitHub connection status
- Listing and syncing repositories from GitHub
- Configuring repository settings (auto-review, review rules)

**Prerequisites**: Phase 1 (Identity & Workspace Foundation) must be implemented first.

---

## How GitHub Integration Works

### Flow Overview

1. User navigates to workspace settings/integrations
2. User clicks "Connect GitHub"
3. Backend returns an installation URL
4. User is redirected to GitHub to install the Sentinel App
5. User selects which repositories to grant access to
6. GitHub redirects back to the backend callback URL
7. Backend redirects to frontend `/workspaces/{slug}/settings/integrations`
8. Frontend shows connected status and can now list repositories

### Key Concepts

| Term | Description |
|------|-------------|
| **Provider** | External source control platform (GitHub). Pre-seeded in database. |
| **Connection** | Authorization link between a Workspace and a Provider |
| **Installation** | The GitHub App installed on a user/org account |
| **Repository** | A GitHub repository that Sentinel has access to |
| **RepositorySettings** | Per-repository configuration (auto-review, rules) |

---

## API Endpoints

All endpoints require authentication and workspace access.

```
Authorization: Bearer {token}
```

### GitHub Connection

#### Get Connection Status
```
GET /api/workspaces/{workspace_id}/github/connection
Authorization: Bearer {token}

Response 200 (connected):
{
  "data": {
    "id": 1,
    "status": "active",
    "status_label": "Active",
    "is_active": true,
    "provider": {
      "id": 1,
      "type": "github",
      "name": "GitHub",
      "label": "GitHub",
      "icon": "github",
      "is_active": true
    },
    "installation": {
      "id": 1,
      "installation_id": 12345678,
      "account_type": "User",
      "account_login": "octocat",
      "account_avatar_url": "https://avatars.githubusercontent.com/u/...",
      "status": "active",
      "status_label": "Active",
      "is_active": true,
      "is_organization": false,
      "repositories_count": 5,
      "suspended_at": null,
      "created_at": "2025-01-09T00:00:00.000000Z",
      "updated_at": "2025-01-09T00:00:00.000000Z"
    },
    "created_at": "2025-01-09T00:00:00.000000Z",
    "updated_at": "2025-01-09T00:00:00.000000Z"
  }
}

Response 200 (not connected):
{
  "data": null
}

Response 200 (provider not configured):
{
  "data": null,
  "message": "GitHub provider not configured."
}
```

#### Initiate GitHub Connection
```
POST /api/workspaces/{workspace_id}/github/connect
Authorization: Bearer {token}

Response 200 (needs redirect):
{
  "data": {
    "id": 1,
    "status": "pending",
    "status_label": "Pending",
    "is_active": false,
    ...
  },
  "installation_url": "https://github.com/apps/sentinel-local/installations/new?state=abc123",
  "message": "Redirect to GitHub to install the app."
}

Response 200 (already connected):
{
  "data": { /* connection object */ },
  "message": "GitHub connection already active."
}

Response 403:
{
  "message": "This action is unauthorized."
}
```

**Frontend behavior**: When `installation_url` is returned, redirect the user to that URL:
```typescript
if (response.installation_url) {
  window.location.href = response.installation_url;
}
```

#### Disconnect GitHub
```
DELETE /api/workspaces/{workspace_id}/github/disconnect
Authorization: Bearer {token}

Response 200:
{
  "message": "GitHub disconnected successfully."
}

Response 403:
{
  "message": "This action is unauthorized."
}

Response 404:
{
  "message": "No GitHub connection found."
}
```

---

### Repositories

#### List Repositories
```
GET /api/workspaces/{workspace_id}/repositories
Authorization: Bearer {token}

Response 200:
{
  "data": [
    {
      "id": 1,
      "github_id": 123456789,
      "name": "my-repo",
      "full_name": "octocat/my-repo",
      "owner": "octocat",
      "private": false,
      "default_branch": "main",
      "language": "TypeScript",
      "description": "My awesome repository",
      "auto_review_enabled": true,
      "settings": {
        "id": 1,
        "auto_review_enabled": true,
        "review_rules": null,
        "updated_at": "2025-01-09T00:00:00.000000Z"
      },
      "created_at": "2025-01-09T00:00:00.000000Z",
      "updated_at": "2025-01-09T00:00:00.000000Z"
    }
  ]
}

Response 200 (no repositories):
{
  "data": []
}
```

#### Get Single Repository
```
GET /api/workspaces/{workspace_id}/repositories/{repository_id}
Authorization: Bearer {token}

Response 200:
{
  "data": {
    "id": 1,
    "github_id": 123456789,
    "name": "my-repo",
    "full_name": "octocat/my-repo",
    "owner": "octocat",
    "private": false,
    "default_branch": "main",
    "language": "TypeScript",
    "description": "My awesome repository",
    "auto_review_enabled": true,
    "settings": { /* settings object */ },
    "installation": { /* installation object */ },
    "created_at": "2025-01-09T00:00:00.000000Z",
    "updated_at": "2025-01-09T00:00:00.000000Z"
  }
}

Response 404:
{
  "message": "Repository not found."
}
```

#### Sync Repositories from GitHub
```
POST /api/workspaces/{workspace_id}/repositories/sync
Authorization: Bearer {token}

Response 200:
{
  "message": "Repositories synced successfully. Added: 3, Updated: 2, Removed: 1",
  "summary": {
    "added": 3,
    "updated": 2,
    "removed": 1
  }
}

Response 422 (no installation):
{
  "message": "No GitHub installation found. Please connect GitHub first."
}

Response 403:
{
  "message": "This action is unauthorized."
}
```

#### Update Repository Settings
```
PATCH /api/workspaces/{workspace_id}/repositories/{repository_id}
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "auto_review_enabled": true,     // optional, boolean
  "review_rules": {                 // optional, object or null
    "focus_areas": ["security", "performance"],
    "ignore_paths": ["tests/**", "docs/**"]
  }
}

Response 200:
{
  "data": { /* repository object with updated settings */ },
  "message": "Repository settings updated successfully."
}

Response 403:
{
  "message": "This action is unauthorized."
}

Response 422:
{
  "message": "The given data was invalid.",
  "errors": {
    "auto_review_enabled": ["The auto review setting must be a boolean value."]
  }
}
```

---

## Status Values

### Connection Status

| Value | Label | Description |
|-------|-------|-------------|
| `pending` | Pending | Connection initiated, awaiting GitHub App installation |
| `active` | Active | Successfully connected and usable |
| `disconnected` | Disconnected | User disconnected the integration |
| `failed` | Failed | Connection failed (error occurred) |

### Installation Status

| Value | Label | Description |
|-------|-------|-------------|
| `active` | Active | Installation is active and working |
| `suspended` | Suspended | User suspended the GitHub App |
| `uninstalled` | Uninstalled | User uninstalled the GitHub App |

---

## Roles & Permissions

| Action | Owner | Admin | Member |
|--------|-------|-------|--------|
| View connection status | Yes | Yes | Yes |
| Connect/Disconnect GitHub | Yes | Yes | No |
| List repositories | Yes | Yes | Yes |
| Sync repositories | Yes | Yes | No |
| Update repository settings | Yes | Yes | No |

---

## Frontend Pages/Components Needed

### Integrations Settings Page

**Route**: `/workspaces/{slug}/settings/integrations`

This is the main page for managing integrations.

#### GitHub Connection Card

Display based on connection state:

**State 1: Not Connected**
```
┌─────────────────────────────────────────────────────┐
│  [GitHub Icon]  GitHub                              │
│                                                     │
│  Connect your GitHub account to enable automated    │
│  code reviews on pull requests.                     │
│                                                     │
│                            [Connect GitHub Button]  │
└─────────────────────────────────────────────────────┘
```

**State 2: Pending (after clicking connect, before completing)**
```
┌─────────────────────────────────────────────────────┐
│  [GitHub Icon]  GitHub              ● Pending       │
│                                                     │
│  Complete the installation on GitHub to activate    │
│  the connection.                                    │
│                                                     │
│  [Continue Setup Button]     [Cancel Button]        │
└─────────────────────────────────────────────────────┘
```

**State 3: Connected**
```
┌─────────────────────────────────────────────────────┐
│  [GitHub Icon]  GitHub              ✓ Connected     │
│                                                     │
│  [Avatar] octocat                                   │
│  5 repositories synced                              │
│  Last synced: 2 hours ago                           │
│                                                     │
│  [View Repositories]    [Sync]    [Disconnect]      │
└─────────────────────────────────────────────────────┘
```

**State 4: Suspended/Failed**
```
┌─────────────────────────────────────────────────────┐
│  [GitHub Icon]  GitHub              ⚠ Suspended     │
│                                                     │
│  The GitHub App has been suspended. Please          │
│  reactivate it in your GitHub settings.             │
│                                                     │
│  [Manage on GitHub]             [Disconnect]        │
└─────────────────────────────────────────────────────┘
```

### Repositories Page

**Route**: `/workspaces/{slug}/repositories`

List all synced repositories with their settings.

```
┌─────────────────────────────────────────────────────────────────┐
│  Repositories                              [Sync from GitHub]   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  [Lock] octocat/private-repo           TypeScript       │   │
│  │  A private repository                                    │   │
│  │  main • Auto-review: ✓ Enabled                          │   │
│  │                                              [Settings]  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  octocat/public-repo                     JavaScript      │   │
│  │  A public repository                                     │   │
│  │  main • Auto-review: ✗ Disabled                         │   │
│  │                                              [Settings]  │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### Repository Settings Page/Modal

**Route**: `/workspaces/{slug}/repositories/{id}/settings` or Modal

```
┌─────────────────────────────────────────────────────┐
│  Repository Settings                                │
│  octocat/my-repo                                    │
│─────────────────────────────────────────────────────│
│                                                     │
│  Auto-Review                                        │
│  ┌─────────────────────────────────────────────┐   │
│  │  [Toggle] Enable automatic code reviews     │   │
│  │  Sentinel will automatically review pull    │   │
│  │  requests when they are opened.             │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  Review Rules (Advanced)                            │
│  ┌─────────────────────────────────────────────┐   │
│  │  Focus Areas: security, performance          │   │
│  │  Ignore Paths: tests/**, docs/**            │   │
│  │                               [Edit Rules]   │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│                          [Cancel]    [Save]         │
└─────────────────────────────────────────────────────┘
```

---

## State Management Suggestions (Pinia)

```typescript
// stores/github.ts
interface GitHubState {
  connection: Connection | null;
  repositories: Repository[];
  isLoading: boolean;
  isSyncing: boolean;
}

interface Connection {
  id: number;
  status: 'pending' | 'active' | 'disconnected' | 'failed';
  status_label: string;
  is_active: boolean;
  provider: Provider;
  installation: Installation | null;
  created_at: string;
  updated_at: string;
}

interface Installation {
  id: number;
  installation_id: number;
  account_type: 'User' | 'Organization';
  account_login: string;
  account_avatar_url: string;
  status: 'active' | 'suspended' | 'uninstalled';
  status_label: string;
  is_active: boolean;
  is_organization: boolean;
  repositories_count?: number;
  suspended_at: string | null;
  created_at: string;
  updated_at: string;
}

interface Repository {
  id: number;
  github_id: number;
  name: string;
  full_name: string;
  owner: string;
  private: boolean;
  default_branch: string;
  language: string | null;
  description: string | null;
  auto_review_enabled: boolean;
  settings: RepositorySettings | null;
  created_at: string;
  updated_at: string;
}

interface RepositorySettings {
  id: number;
  auto_review_enabled: boolean;
  review_rules: Record<string, unknown> | null;
  updated_at: string;
}
```

---

## GitHub Callback Handling

The backend handles the GitHub App installation callback and redirects to:

```
/workspaces/{slug}/settings/integrations
```

With a flash message (via redirect `with()`):
- Success: `success=GitHub connected successfully!`
- Error: `error={error_message}`

**Frontend should**:
1. Check for `success` or `error` query params or session flash
2. Display appropriate toast/notification
3. Refresh the connection status

---

## Important Implementation Notes

1. **Check connection before showing repositories** - Don't show the repositories page/section if not connected.

2. **Handle the redirect flow** - When user clicks "Connect GitHub":
   ```typescript
   const connect = async () => {
     const response = await api.post(`/workspaces/${workspaceId}/github/connect`);
     if (response.installation_url) {
       // User needs to complete installation on GitHub
       window.location.href = response.installation_url;
     } else {
       // Already connected
       refreshConnection();
     }
   };
   ```

3. **Sync is async** - The sync operation fetches from GitHub. Show a loading state.

4. **Installation can be on User or Organization** - The `is_organization` flag indicates this. Show appropriate UI (e.g., org avatar vs user avatar).

5. **Repository settings are lazy-created** - A repository may not have settings until first update. Handle `settings: null` gracefully.

6. **Private repositories** - Show a lock icon for `private: true` repositories.

7. **Auto-review toggle** - The main action users will take. Make it prominent and easy to toggle.

8. **Handle suspended/uninstalled states** - Guide users to GitHub to resolve.

---

## API Client Examples

```typescript
// composables/useGitHub.ts
export const useGitHub = (workspaceId: number) => {
  const { $api } = useApi();

  const getConnection = () =>
    $api(`/workspaces/${workspaceId}/github/connection`);

  const connect = () =>
    $api(`/workspaces/${workspaceId}/github/connect`, { method: 'POST' });

  const disconnect = () =>
    $api(`/workspaces/${workspaceId}/github/disconnect`, { method: 'DELETE' });

  const getRepositories = () =>
    $api(`/workspaces/${workspaceId}/repositories`);

  const syncRepositories = () =>
    $api(`/workspaces/${workspaceId}/repositories/sync`, { method: 'POST' });

  const getRepository = (repositoryId: number) =>
    $api(`/workspaces/${workspaceId}/repositories/${repositoryId}`);

  const updateRepository = (repositoryId: number, data: UpdateRepositoryData) =>
    $api(`/workspaces/${workspaceId}/repositories/${repositoryId}`, {
      method: 'PATCH',
      body: data,
    });

  return {
    getConnection,
    connect,
    disconnect,
    getRepositories,
    syncRepositories,
    getRepository,
    updateRepository,
  };
};

interface UpdateRepositoryData {
  auto_review_enabled?: boolean;
  review_rules?: Record<string, unknown> | null;
}
```

---

## Quick Start Checklist

- [ ] Add GitHub integration to workspace store
- [ ] Create integrations settings page
- [ ] Implement GitHub connection card component
- [ ] Handle connect flow with redirect
- [ ] Handle callback return (flash messages)
- [ ] Implement disconnect confirmation
- [ ] Create repositories list page
- [ ] Implement repository sync with loading state
- [ ] Create repository settings modal/page
- [ ] Implement auto-review toggle
- [ ] Handle connection status states (pending, active, suspended, etc.)
- [ ] Add loading states throughout
- [ ] Handle error states and display messages
- [ ] Role-based UI (hide actions for Members)

---

## Related Webhook Events

The backend processes these GitHub webhook events (for reference):

| Event | Action |
|-------|--------|
| `installation` | App installed/uninstalled/suspended |
| `installation_repositories` | Repositories added/removed from installation |
| `pull_request` | PR opened/synchronized/closed (triggers reviews) |

These are handled server-side. The frontend doesn't need to handle webhooks directly, but should refresh data when appropriate (e.g., after user returns from GitHub).

---

Good luck! The backend is ready and tested.
