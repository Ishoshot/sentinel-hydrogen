# Sentinel - Development State & Knowledge Graph

> **Purpose**: This document provides persistent memory across development sessions. Update this document as features are completed.

---

## Product Overview

**Sentinel** is a source-control-native AI-powered code review platform designed to help engineering teams maintain code quality, correctness, and security at scale.

### Core Value Proposition
- Calm, high-signal automated code reviews
- Actionable insights that help teams ship with confidence
- Configurable policies and enterprise-grade architecture
- No noise, no friction - integrates into existing workflows

### Guiding Principles
- Calm over clever
- Signal over noise
- Clarity over automation
- Control over opacity
- Trust is earned, not assumed

---

## Technical Stack

| Component | Technology |
|-----------|------------|
| Backend Language | PHP 8.4 |
| Backend Framework | Laravel 12 |
| Database | PostgreSQL |
| Cache/Queue | Redis |
| AI Routing | PrismPHP |
| Workers | Laravel Queue Workers (Horizon) |
| Testing | Pest 4 |
| Frontend | Vue/Nuxt 4 (separate repository) |
| Auth | Laravel Socialite + Sanctum |
| Code Style | Laravel Pint |

### Architecture Pattern
- **API-only backend** - Frontend is a separate Vue/Nuxt 4 project
- **Multi-tenant** - All data scoped to Workspace
- **Event-driven** - Domain events for decoupling
- **Action pattern** - Controllers delegate to Action classes
- **Domain-driven structure** - Organized by domain, not technical layer

---

## Domain Model

### Core Entities

| Entity | Description |
|--------|-------------|
| **User** | Individual authenticated via OAuth (GitHub/Google) |
| **Workspace** | Primary tenant boundary, owns all data |
| **Team** | Membership container within Workspace (1:1 with Workspace) |
| **TeamMember** | User's membership in a Team with role |
| **Invitation** | Pending request to join a Team |
| **ProviderIdentity** | OAuth provider link to User |

### Future Entities (Not Yet Implemented)

| Entity | Description |
|--------|-------------|
| **Provider** | External source control platform (GitHub, GitLab) |
| **Connection** | Authorization between Workspace and Provider |
| **Installation** | Sentinel installed in Provider account |
| **Repository** | Source code repository connected to Sentinel |
| **RepositorySettings** | Configuration for a Repository |
| **Run** | Single execution of Sentinel's review process |
| **Finding** | Issue identified during a Run |
| **Annotation** | Finding surfaced in source control platform |
| **Policy** | Collection of rules and thresholds |
| **Plan** | Subscription tier defining limits |
| **Subscription** | Active billing state of Workspace |
| **UsageRecord** | Metered resource consumption |
| **ProviderKey** | BYOK credential for AI provider |

---

## Feature Implementation Roadmap

### Phase 1: Identity & Workspace Foundation
**Status: COMPLETE**

- [x] OAuth login (GitHub + Google)
- [x] User creation & provider identity linking
- [x] Workspace creation on first login
- [x] Single Team per Workspace (auto-created)
- [x] Member invitation & role assignment (Owner, Admin, Member)
- [x] Workspace switcher
- [x] Renaming workspace renames team automatically
- [x] All 86 tests passing (including arch tests)

### Phase 2: Source Control Integration (GitHub)
**Status: NOT STARTED**

- [ ] GitHub OAuth app configuration
- [ ] GitHub App installation flow
- [ ] Repository discovery and selection
- [ ] Webhook registration
- [ ] Connection status management

### Phase 3: Repository Management
**Status: NOT STARTED**

- [ ] Repository CRUD operations
- [ ] Repository settings (enabled/disabled)
- [ ] Default branch configuration
- [ ] Review trigger configuration

### Phase 4: Review System Core
**Status: NOT STARTED**

- [ ] Run creation from webhook events
- [ ] Policy configuration
- [ ] AI provider routing (PrismPHP)
- [ ] Finding generation and storage
- [ ] Annotation posting to GitHub

### Phase 5: Plans & Billing
**Status: NOT STARTED**

- [ ] Plan model with limits
- [ ] Subscription management
- [ ] Usage metering
- [ ] Limit enforcement
- [ ] BYOK provider key management

### Phase 6: Dashboards & Analytics
**Status: NOT STARTED**

- [ ] Workspace dashboard
- [ ] Repository-level metrics
- [ ] Finding trends
- [ ] Usage reports

---

## Completed Implementation Details

### Identity & Workspace Foundation

#### Database Tables Created
```
users (modified: password nullable, avatar_url added)
workspaces (id, name, slug, owner_id, settings)
teams (id, name, workspace_id)
team_members (id, user_id, team_id, workspace_id, role, joined_at)
invitations (id, email, workspace_id, team_id, invited_by_id, role, token, expires_at, accepted_at)
provider_identities (id, user_id, provider, provider_user_id, email, name, avatar_url, access_token, refresh_token, token_expires_at)
```

#### Enums
- `App\Enums\TeamRole` - Owner, Admin, Member (with permission helpers)
- `App\Enums\OAuthProvider` - GitHub, Google

#### Models
- `App\Models\User` - HasApiTokens trait, workspace relationships
- `App\Models\Workspace` - owner, team, teamMembers, members
- `App\Models\Team` - workspace, members
- `App\Models\TeamMember` - user, team, workspace
- `App\Models\Invitation` - workspace, team, invitedBy
- `App\Models\ProviderIdentity` - user (encrypted tokens)

#### Actions
- `App\Actions\Auth\HandleOAuthCallback`
- `App\Actions\Workspaces\CreateWorkspace`
- `App\Actions\Workspaces\CreateWorkspaceForNewUser`
- `App\Actions\Workspaces\UpdateWorkspace`
- `App\Actions\Workspaces\DeleteWorkspace`
- `App\Actions\Teams\UpdateTeamMemberRole`
- `App\Actions\Teams\RemoveTeamMember`
- `App\Actions\Invitations\CreateInvitation`
- `App\Actions\Invitations\AcceptInvitation`
- `App\Actions\Invitations\CancelInvitation`

#### Controllers
- `App\Http\Controllers\Auth\OAuthController`
- `App\Http\Controllers\WorkspaceController`
- `App\Http\Controllers\TeamMemberController`
- `App\Http\Controllers\InvitationController`

#### API Resources
- `App\Http\Resources\UserResource`
- `App\Http\Resources\WorkspaceResource`
- `App\Http\Resources\TeamResource`
- `App\Http\Resources\TeamMemberResource`
- `App\Http\Resources\InvitationResource`

#### Routes (routes/api.php)
```php
POST /api/invitations/{token}/accept (public, returns 401 with info if unauthenticated)

// Authenticated routes
GET  /api/user
POST /api/logout
GET  /api/workspaces
POST /api/workspaces

// Workspace-scoped routes (middleware: workspace.access)
GET    /api/workspaces/{workspace}
POST   /api/workspaces/{workspace}/switch
PATCH  /api/workspaces/{workspace}
DELETE /api/workspaces/{workspace}
GET    /api/workspaces/{workspace}/members
PATCH  /api/workspaces/{workspace}/members/{member}
DELETE /api/workspaces/{workspace}/members/{member}
GET    /api/workspaces/{workspace}/invitations
POST   /api/workspaces/{workspace}/invitations
DELETE /api/workspaces/{workspace}/invitations/{invitation}
```

#### OAuth Routes (routes/web.php)
```php
GET /auth/{provider}/redirect
GET /auth/{provider}/callback
```

#### Middleware
- `EnsureWorkspaceAccess` - Verifies user belongs to workspace
- `EnsureWorkspaceRole` - Checks required role(s)

#### Policies
- `WorkspacePolicy` - view, update, delete, manageMembers, invite
- `TeamMemberPolicy` - update, delete
- `InvitationPolicy` - create, delete

#### Tests (64 total, all passing)
- `tests/Feature/Auth/OAuthLoginTest.php` (9 tests)
- `tests/Feature/Workspaces/CreateWorkspaceTest.php` (10 tests)
- `tests/Feature/Workspaces/UpdateWorkspaceTest.php` (8 tests)
- `tests/Feature/Workspaces/WorkspaceSwitcherTest.php` (6 tests)
- `tests/Feature/Members/TeamMemberManagementTest.php` (10 tests)
- `tests/Feature/Invitations/CreateInvitationTest.php` (12 tests)
- `tests/Feature/Invitations/AcceptInvitationTest.php` (9 tests)

---

## Conventions Established

### API Response Format
- Use Laravel API Resources for all responses
- For paginated data: wrap items in resources while keeping Laravel's default pagination structure
```php
$items->setCollection($items->getCollection()->map(fn ($item) => new ItemResource($item)));
return response()->json($items, 200);
```

### Authorization
- Use `Gate::authorize()` in controllers
- Create Policy classes for each model
- Roles: Owner > Admin > Member

### OAuth Flow
1. Frontend redirects to: `{BACKEND_URL}/auth/{provider}/redirect`
2. User authenticates with provider
3. Backend redirects to: `{FRONTEND_URL}/auth/callback?token={sanctum_token}`
4. On error: `{FRONTEND_URL}/auth/error?message={error_message}`

### Environment Configuration
```env
FRONTEND_URL=http://localhost:3000
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

### Testing
- Use Pest framework
- Use `route()` helper with named routes in all tests
- Use factories with states for model creation
- Use `actingAs($user, 'sanctum')` for authenticated requests

---

## Frontend Handover Document Location
A handover document for the frontend agent was created at:
`FRONTEND_HANDOVER_IDENTITY_WORKSPACE.md` (root of project)

This document contains:
- Full API endpoint documentation with request/response examples
- OAuth flow details
- Role permissions matrix
- State management suggestions
- Component/page requirements

---

## Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/product/PRD.md` | Product requirements |
| `docs/product/GLOSSARY.md` | Domain vocabulary |
| `docs/product/PLANS_AND_LIMITS.md` | Subscription model |
| `docs/product/UX_PRINCIPLES.md` | UX guidelines |
| `docs/backend/BACKEND_ARCHITECTURE.md` | System architecture |
| `docs/backend/DATA_MODEL.md` | Database design |
| `docs/backend/AUTHENTICATION.md` | Auth implementation |
| `docs/backend/CODING_STANDARDS.md` | Code conventions |
| `docs/backend/TESTING_STRATEGY.md` | Test guidelines |
| `docs/backend/OTHERS.md` | API conventions |

---

## Next Implementation Steps

1. **Source Control Integration (GitHub)**
   - Configure GitHub App in GitHub Developer settings
   - Implement Installation flow
   - Build repository discovery

2. **Repository Management**
   - Create migrations for repositories, repository_settings
   - Build CRUD API endpoints
   - Implement webhook handlers

3. **Review System**
   - Create migrations for runs, findings, annotations
   - Integrate PrismPHP for AI routing
   - Build review execution pipeline

---

*Last Updated: 2026-01-09*
*Phase Completed: Identity & Workspace Foundation*
