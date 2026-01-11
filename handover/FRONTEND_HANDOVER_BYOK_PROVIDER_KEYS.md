# Frontend Implementation: BYOK Provider Key Management (Phase 6)

> **This is a handover document for the frontend agent.**

## Context

The backend for Sentinel (Laravel 12 API) has implemented **BYOK (Bring Your Own Key) Provider Key Management** (Phase 6). You need to implement the corresponding frontend in **Nuxt 4**.

This feature covers:

-   Adding AI provider API keys (Anthropic, OpenAI) for a repository
-   Listing configured provider keys (without exposing actual key values)
-   Deleting provider keys
-   Role-based access control (only owners/admins can manage keys)

**Prerequisites**: Phase 2 (GitHub Integration) and Phase 5 (Repository Configuration) must be implemented first.

---

## Why This Feature Exists

Sentinel requires AI provider API keys to perform code reviews. Instead of using a shared system key, users can bring their own keys (BYOK) to:

-   Use their own API quotas and billing
-   Have full control over their API usage
-   Remove dependency on Sentinel's API allocations

**CRITICAL SECURITY NOTE**: API keys are stored encrypted in the database. The API **NEVER** returns the actual key value after creation. Users can only see that a key exists, not its value.

---

## How BYOK Works

### Flow Overview

1. User navigates to repository settings
2. User sees "API Keys" or "Provider Keys" section
3. User selects a provider (Anthropic or OpenAI) and enters their API key
4. Key is sent to backend via HTTPS, encrypted, and stored
5. User sees confirmation that key is configured (shows provider name, never the key)
6. When reviews run, Sentinel uses the configured key for that repository
7. User can delete a key at any time

### Key Concepts

| Term             | Description                                          |
| ---------------- | ---------------------------------------------------- |
| **Provider**     | AI provider (Anthropic for Claude, OpenAI for GPT-4) |
| **Provider Key** | An encrypted API key stored for a repository         |
| **BYOK**         | Bring Your Own Key - user provides their own API key |

### Supported Providers

| Provider  | Value       | Label              | Example Key Format |
| --------- | ----------- | ------------------ | ------------------ |
| Anthropic | `anthropic` | Anthropic (Claude) | `sk-ant-api03-...` |
| OpenAI    | `openai`    | OpenAI (GPT-4)     | `sk-proj-...`      |

---

## API Endpoints

All endpoints require authentication and workspace access.

```
Authorization: Bearer {token}
```

### List Provider Keys

Returns all configured provider keys for a repository. **Never includes the actual key value.**

```
GET /api/workspaces/{workspace_id}/repositories/{repository_id}/provider-keys
Authorization: Bearer {token}

Response 200:
{
  "data": [
    {
      "id": 1,
      "provider": "anthropic",
      "provider_label": "Anthropic (Claude)",
      "created_at": "2025-01-11T10:00:00.000000Z",
      "updated_at": "2025-01-11T10:00:00.000000Z"
    },
    {
      "id": 2,
      "provider": "openai",
      "provider_label": "OpenAI (GPT-4)",
      "created_at": "2025-01-11T11:00:00.000000Z",
      "updated_at": "2025-01-11T11:00:00.000000Z"
    }
  ]
}

Response 200 (no keys configured):
{
  "data": []
}
```

**Access Control**: Any workspace member can view the list.

### Store Provider Key

Creates or updates a provider key for a repository. If a key already exists for the same provider, it is updated (upsert behavior).

```
POST /api/workspaces/{workspace_id}/repositories/{repository_id}/provider-keys
Authorization: Bearer {token}
Content-Type: application/json

Request Body:
{
  "provider": "anthropic",
  "key": "sk-ant-api03-your-actual-api-key-here"
}

Response 201:
{
  "data": {
    "id": 1,
    "provider": "anthropic",
    "provider_label": "Anthropic (Claude)",
    "created_at": "2025-01-11T10:00:00.000000Z",
    "updated_at": "2025-01-11T10:00:00.000000Z"
  },
  "message": "Provider key configured successfully."
}

Response 403 (not authorized):
{
  "message": "This action is unauthorized."
}

Response 422 (validation error):
{
  "message": "The provider field is required.",
  "errors": {
    "provider": ["The provider field is required."],
    "key": ["The key field is required."]
  }
}
```

**Access Control**: Only workspace owners and admins can create/update keys.

**Validation Rules**:

-   `provider`: Required, must be one of: `anthropic`, `openai`
-   `key`: Required, minimum 10 characters

### Delete Provider Key

Removes a provider key from a repository.

```
DELETE /api/workspaces/{workspace_id}/repositories/{repository_id}/provider-keys/{provider_key_id}
Authorization: Bearer {token}

Response 200:
{
  "message": "Provider key deleted successfully."
}

Response 403 (not authorized):
{
  "message": "This action is unauthorized."
}

Response 404 (key not found or wrong repository):
{
  "message": "Not Found"
}
```

**Access Control**: Only workspace owners and admins can delete keys.

---

## Frontend Pages/Components Needed

### Repository Settings Page

**Route**: `/workspaces/{slug}/repositories/{id}/settings`

Add a new "API Keys" or "Provider Keys" section to the repository settings page.

#### No Keys Configured

```
┌─────────────────────────────────────────────────────────────────────────┐
│  API Keys                                                               │
│                                                                         │
│  Configure your AI provider API keys to enable code reviews for this   │
│  repository. Your keys are encrypted and never displayed after saving. │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  No API keys configured                                         │   │
│  │                                                                  │   │
│  │  Add an API key to enable Sentinel reviews for this repository. │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│                                              [+ Add API Key]            │
└─────────────────────────────────────────────────────────────────────────┘
```

#### Keys Configured

```
┌─────────────────────────────────────────────────────────────────────────┐
│  API Keys                                                               │
│                                                                         │
│  Your API keys are encrypted and never displayed after saving.         │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Anthropic (Claude)                                   [Delete]  │   │
│  │  Configured • Added Jan 11, 2025                                │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  OpenAI (GPT-4)                                       [Delete]  │   │
│  │  Configured • Added Jan 11, 2025                                │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│                                              [+ Add API Key]            │
└─────────────────────────────────────────────────────────────────────────┘
```

### Add API Key Modal/Form

When user clicks "Add API Key", show a modal or form:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Add API Key                                                     [X]   │
│─────────────────────────────────────────────────────────────────────────│
│                                                                         │
│  Provider                                                               │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Select a provider...                                      ▼    │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  API Key                                                                │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  ••••••••••••••••••••••••••••••••                               │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│  Your key is encrypted and will never be displayed again.              │
│                                                                         │
│  ⚠️ If you already have a key for this provider, it will be replaced. │
│                                                                         │
│                                              [Cancel]  [Save API Key]   │
└─────────────────────────────────────────────────────────────────────────┘
```

**Provider dropdown options**:

-   Anthropic (Claude)
-   OpenAI (GPT-4)

**Key input**:

-   Use `type="password"` to mask the input
-   Show a toggle to reveal/hide the key while typing
-   After saving, NEVER offer to show the key again

### Delete Confirmation

When user clicks "Delete" on a key:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Delete API Key?                                                 [X]   │
│─────────────────────────────────────────────────────────────────────────│
│                                                                         │
│  Are you sure you want to delete the Anthropic (Claude) API key?       │
│                                                                         │
│  This will disable Sentinel reviews for this repository until a new    │
│  key is configured.                                                    │
│                                                                         │
│                                              [Cancel]  [Delete Key]     │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## State Management Suggestions (Pinia)

### TypeScript Interfaces

```typescript
// types/provider-key.ts
export interface ProviderKey {
    id: number;
    provider: "anthropic" | "openai";
    provider_label: string;
    created_at: string;
    updated_at: string;
}

export interface StoreProviderKeyRequest {
    provider: "anthropic" | "openai";
    key: string;
}

export type AiProvider = "anthropic" | "openai";

export const AI_PROVIDERS: { value: AiProvider; label: string }[] = [
    { value: "anthropic", label: "Anthropic (Claude)" },
    { value: "openai", label: "OpenAI (GPT-4)" },
];
```

### Composable

```typescript
// composables/useProviderKeys.ts
export const useProviderKeys = (workspaceId: number, repositoryId: number) => {
    const { $api } = useApi();

    const providerKeys = ref<ProviderKey[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    const fetchProviderKeys = async () => {
        isLoading.value = true;
        error.value = null;
        try {
            const response = await $api(
                `/workspaces/${workspaceId}/repositories/${repositoryId}/provider-keys`
            );
            providerKeys.value = response.data;
        } catch (e) {
            error.value = "Failed to load provider keys";
        } finally {
            isLoading.value = false;
        }
    };

    const storeProviderKey = async (provider: AiProvider, key: string) => {
        const response = await $api(
            `/workspaces/${workspaceId}/repositories/${repositoryId}/provider-keys`,
            {
                method: "POST",
                body: { provider, key },
            }
        );
        // Refresh the list after storing
        await fetchProviderKeys();
        return response;
    };

    const deleteProviderKey = async (providerKeyId: number) => {
        await $api(
            `/workspaces/${workspaceId}/repositories/${repositoryId}/provider-keys/${providerKeyId}`,
            { method: "DELETE" }
        );
        // Remove from local state
        providerKeys.value = providerKeys.value.filter(
            (k) => k.id !== providerKeyId
        );
    };

    // Check if a specific provider is configured
    const hasProvider = (provider: AiProvider) => {
        return providerKeys.value.some((k) => k.provider === provider);
    };

    // Get available providers (ones not yet configured)
    const availableProviders = computed(() => {
        return AI_PROVIDERS.filter((p) => !hasProvider(p.value));
    });

    return {
        providerKeys,
        isLoading,
        error,
        fetchProviderKeys,
        storeProviderKey,
        deleteProviderKey,
        hasProvider,
        availableProviders,
    };
};
```

---

## Role-Based Access Control

| Action             | Owner | Admin | Member |
| ------------------ | ----- | ----- | ------ |
| View provider keys | Yes   | Yes   | Yes    |
| Add/update key     | Yes   | Yes   | No     |
| Delete key         | Yes   | Yes   | No     |

**Frontend behavior**:

-   Hide "Add API Key" button for members
-   Hide "Delete" buttons for members
-   Show a message explaining they need admin access to manage keys

```typescript
// Check if user can manage keys
const canManageKeys = computed(() => {
    const role = currentUser.value?.roleInWorkspace(workspace.value);
    return role === "owner" || role === "admin";
});
```

---

## Important Implementation Notes

1. **Never show the key value** - After a key is saved, it cannot be retrieved. The API never returns `encrypted_key`. Show "Configured" or similar instead.

2. **Use password input** - When entering a key, use `type="password"` for the input field. Optionally provide a "show/hide" toggle while typing.

3. **Upsert behavior** - If a key already exists for a provider, creating a new one replaces it. Warn users about this in the UI.

4. **Handle 403 gracefully** - If a member tries to add/delete a key (shouldn't happen if UI is correct), handle the 403 response gracefully.

5. **Optimistic updates** - After deleting a key, you can optimistically remove it from the UI before the API responds.

6. **Provider dropdown** - Show friendly labels ("Anthropic (Claude)") but send the API value ("anthropic").

7. **Loading states** - Show loading indicators when fetching, saving, or deleting keys.

8. **Error handling** - Display validation errors from the API (e.g., "API key must be at least 10 characters").

9. **Success feedback** - Show a toast/notification when a key is successfully configured or deleted.

10. **No "view key" option** - Unlike password managers, there should be NO option to view an existing key. Users can only delete and re-add.

---

## Activity Log Integration

When keys are managed, activities are logged:

| Activity Type          | Description                                 |
| ---------------------- | ------------------------------------------- |
| `provider_key.updated` | API key was configured (created or updated) |
| `provider_key.deleted` | API key was deleted                         |

These appear in the workspace activity feed. The activity description includes the provider name but never the key value.

---

## Error States

### No Provider Keys & Review Fails

If a repository has no provider keys configured and a PR is opened, the review will fail with a "No provider keys configured" error. This error appears in the run details.

Consider showing a warning in the repository settings if no keys are configured:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ⚠️ No API keys configured                                              │
│                                                                         │
│  Sentinel cannot run reviews without an AI provider API key.           │
│  Add an API key below to enable reviews.                               │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Quick Start Checklist

-   [ ] Create `ProviderKey` TypeScript interface
-   [ ] Create `useProviderKeys` composable
-   [ ] Add "API Keys" section to repository settings page
-   [ ] Implement list view showing configured providers
-   [ ] Implement "Add API Key" modal with provider dropdown and password input
-   [ ] Implement delete confirmation dialog
-   [ ] Add role-based UI visibility (hide buttons for members)
-   [ ] Handle loading and error states
-   [ ] Show success toasts for create/delete actions
-   [ ] Add warning when no keys are configured
-   [ ] Never display or offer to display existing key values

---

## Related Features

### Run Error: No Provider Key

When a run fails due to missing provider key, the run metadata shows:

```json
{
    "status": "failed",
    "error_message": "No provider keys configured for this repository"
}
```

Display this error clearly in the run details page and link to the repository settings to add a key.

---

Good luck! The backend is ready and tested.
