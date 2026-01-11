# Frontend Implementation: Identity & Workspace Foundation

> **This is a handover document for the frontend agent. Delete this file after use.**

## Context

The backend for Sentinel (Laravel 12 API) has implemented the **Identity & Workspace Foundation** feature. You need to implement the corresponding frontend in **Vue/Nuxt 4**.

Sentinel is an AI-powered code review platform. This feature covers:
- OAuth-only authentication (GitHub, Google) - no password auth
- User session management with API tokens
- Workspace creation and management
- Team member management with roles
- Member invitation system

---

## Backend API Details

### Base URL
The backend API is available at the URL configured in your environment. All API routes are prefixed with `/api`.

### Authentication

#### OAuth Flow
1. Frontend redirects user to: `{BACKEND_URL}/auth/{provider}/redirect` (provider: `github` or `google`)
2. User authenticates with the OAuth provider
3. Backend processes callback and redirects to: `{FRONTEND_URL}/auth/callback?token={sanctum_token}`
4. Frontend stores the token and uses it for all subsequent API requests
5. On error, backend redirects to: `{FRONTEND_URL}/auth/error?message={error_message}`

#### API Authentication
All authenticated requests require the header:
```
Authorization: Bearer {token}
```

---

## API Endpoints

### Authentication

#### Get Current User
```
GET /api/user
Authorization: Bearer {token}

Response 200:
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "avatar_url": "https://...",
    "email_verified_at": "2024-01-01T00:00:00.000000Z",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

#### Logout
```
POST /api/logout
Authorization: Bearer {token}

Response 200:
{
  "message": "Successfully logged out."
}
```

---

### Workspaces

#### List User's Workspaces
```
GET /api/workspaces
Authorization: Bearer {token}

Response 200:
{
  "data": [
    {
      "id": 1,
      "name": "My Workspace",
      "slug": "my-workspace-abc123",
      "settings": null,
      "owner": { "id": 1, "name": "John Doe", ... },
      "team": { "id": 1, "name": "My Workspace", ... },
      "members_count": 3,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

#### Create Workspace
```
POST /api/workspaces
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "name": "New Workspace"  // required, string, max 255
}

Response 201:
{
  "data": { /* workspace object */ },
  "message": "Workspace created successfully."
}

Response 422 (validation error):
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

#### Get Workspace
```
GET /api/workspaces/{workspace_id}
Authorization: Bearer {token}

Response 200:
{
  "data": { /* workspace object with team.members.user loaded */ }
}

Response 403 (not a member):
{
  "message": "You do not have access to this workspace."
}
```

#### Update Workspace
```
PATCH /api/workspaces/{workspace_id}
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "name": "Updated Name"  // required
}

Response 200:
{
  "data": { /* workspace object */ },
  "message": "Workspace updated successfully."
}

Response 403 (not owner/admin):
{
  "message": "This action is unauthorized."
}
```

#### Delete Workspace
```
DELETE /api/workspaces/{workspace_id}
Authorization: Bearer {token}

Response 200:
{
  "message": "Workspace deleted successfully."
}

Response 403 (not owner):
{
  "message": "This action is unauthorized."
}
```

#### Switch Workspace
```
POST /api/workspaces/{workspace_id}/switch
Authorization: Bearer {token}

Response 200:
{
  "data": { /* workspace object */ },
  "message": "Switched to My Workspace"
}
```

---

### Team Members

#### List Workspace Members
```
GET /api/workspaces/{workspace_id}/members
Authorization: Bearer {token}

Response 200:
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "team_id": 1,
      "workspace_id": 1,
      "role": "owner",
      "role_label": "Owner",
      "user": { "id": 1, "name": "John Doe", ... },
      "joined_at": "2024-01-01T00:00:00.000000Z",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

#### Update Member Role
```
PATCH /api/workspaces/{workspace_id}/members/{member_id}
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "role": "admin"  // required, one of: "admin", "member"
}

Response 200:
{
  "data": { /* team member object */ },
  "message": "Member role updated successfully."
}

Response 403:
- Not authorized to manage members
- Cannot change owner's role

Response 422:
- Invalid role value
```

#### Remove Member
```
DELETE /api/workspaces/{workspace_id}/members/{member_id}
Authorization: Bearer {token}

Response 200:
{
  "message": "Member removed successfully."
}

Response 403:
- Not authorized to remove members
- Cannot remove the owner
```

---

### Invitations

#### List Pending Invitations
```
GET /api/workspaces/{workspace_id}/invitations
Authorization: Bearer {token}

Response 200:
{
  "data": [
    {
      "id": 1,
      "email": "invited@example.com",
      "workspace_id": 1,
      "team_id": 1,
      "role": "member",
      "role_label": "Member",
      "invited_by": { "id": 1, "name": "John Doe", ... },
      "workspace": { /* workspace object */ },
      "is_expired": false,
      "is_accepted": false,
      "is_pending": true,
      "expires_at": "2024-01-08T00:00:00.000000Z",
      "accepted_at": null,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

#### Create Invitation
```
POST /api/workspaces/{workspace_id}/invitations
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "email": "newmember@example.com",  // required, valid email
  "role": "member"                    // required, one of: "admin", "member"
}

Response 201:
{
  "data": { /* invitation object */ },
  "message": "Invitation sent successfully."
}

Response 403 (not owner/admin):
{
  "message": "This action is unauthorized."
}

Response 422:
- Invalid email
- User already a member
- Cannot invite with "owner" role
```

#### Cancel Invitation
```
DELETE /api/workspaces/{workspace_id}/invitations/{invitation_id}
Authorization: Bearer {token}

Response 200:
{
  "message": "Invitation cancelled."
}
```

#### Accept Invitation (by token)
```
POST /api/invitations/{token}/accept
Authorization: Bearer {token}  // Optional - see below

// If authenticated:
Response 200:
{
  "message": "You have joined My Workspace",
  "data": { /* invitation object with workspace */ }
}

// If NOT authenticated:
Response 401:
{
  "message": "Authentication required to accept this invitation.",
  "invitation": {
    "workspace_name": "My Workspace",
    "role": "member",
    "email": "invited@example.com"
  }
}

Response 404: Invalid token
Response 409: Already accepted
Response 410: Expired
Response 422: User already in workspace
```

---

## Roles & Permissions

| Role   | Value    | Can Manage Settings | Can Manage Members | Can Invite |
|--------|----------|---------------------|-------------------|------------|
| Owner  | `owner`  | ✅                   | ✅                 | ✅          |
| Admin  | `admin`  | ✅                   | ✅                 | ✅          |
| Member | `member` | ❌                   | ❌                 | ❌          |

- Only **Owner** can delete a workspace
- Only **Owner** can transfer ownership (not implemented yet)
- **Owner** role cannot be changed or removed
- **Admin** cannot change another admin's role (only owner can)

---

## Frontend Pages/Components Needed

### Authentication
1. **Login Page** (`/login`)
   - Two buttons: "Sign in with GitHub", "Sign in with Google"
   - Clicking redirects to `{BACKEND_URL}/auth/{provider}/redirect`

2. **Auth Callback Page** (`/auth/callback`)
   - Receives `?token=...` query param
   - Stores token (localStorage, cookie, or Pinia store)
   - Redirects to dashboard or intended URL

3. **Auth Error Page** (`/auth/error`)
   - Receives `?message=...` query param
   - Displays error message
   - Link back to login

### Workspace Management
4. **Workspace Switcher** (dropdown/modal in header)
   - Lists all user's workspaces
   - Shows current workspace
   - Option to create new workspace
   - Click to switch workspace

5. **Workspace Settings Page** (`/workspaces/{id}/settings`)
   - Edit workspace name (owner/admin only)
   - Delete workspace (owner only)
   - Show danger zone with confirmation

### Team Members
6. **Members Page** (`/workspaces/{id}/members`)
   - List all members with roles
   - Role badge (Owner, Admin, Member)
   - Change role dropdown (owner/admin only)
   - Remove member button (owner/admin only, not for owner)

### Invitations
7. **Invitations Section** (on Members page or separate tab)
   - List pending invitations
   - Form to invite: email input + role select
   - Cancel invitation button

8. **Accept Invitation Page** (`/invitations/{token}`)
   - If not logged in: Show invitation details, prompt to sign in
   - If logged in: Accept and redirect to workspace

### User Menu
9. **User Dropdown** (in header)
   - User avatar + name
   - Link to account settings (future)
   - Logout button

---

## State Management Suggestions (Pinia)

```typescript
// stores/auth.ts
interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
}

// stores/workspace.ts
interface WorkspaceState {
  workspaces: Workspace[];
  currentWorkspace: Workspace | null;
  members: TeamMember[];
  invitations: Invitation[];
}
```

---

## Important Notes

1. **No password authentication** - OAuth only (GitHub, Google)
2. **First login creates workspace** - New users automatically get a personal workspace
3. **Single team per workspace** - Team is auto-created with workspace, shares same name
4. **Renaming workspace renames team** - They stay in sync
5. **Invitation tokens are in URL** - `/invitations/{token}` format
6. **Invitations expire** - Check `is_expired` flag
7. **API uses Sanctum tokens** - Store securely, include in all requests

---

## Example API Client Setup (Nuxt)

```typescript
// composables/useApi.ts
export const useApi = () => {
  const config = useRuntimeConfig();
  const token = useCookie('auth_token');

  const $api = $fetch.create({
    baseURL: config.public.apiBaseUrl,
    headers: {
      Accept: 'application/json',
    },
    onRequest({ options }) {
      if (token.value) {
        options.headers = {
          ...options.headers,
          Authorization: `Bearer ${token.value}`,
        };
      }
    },
  });

  return { $api };
};
```

---

## Quick Start Checklist

- [ ] Set up API client with auth token handling
- [ ] Create auth store (Pinia)
- [ ] Create workspace store (Pinia)
- [ ] Implement login page with OAuth buttons
- [ ] Implement auth callback handler
- [ ] Implement auth error page
- [ ] Create layout with workspace switcher
- [ ] Implement workspace settings page
- [ ] Implement members management page
- [ ] Implement invitation system
- [ ] Implement accept invitation page
- [ ] Add route guards for authenticated routes
- [ ] Add loading states and error handling

---

Good luck! The backend is fully tested and ready. All 64 tests pass.
