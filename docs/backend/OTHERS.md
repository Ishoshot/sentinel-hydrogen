# Sentinel – API Conventions & Additional Standards

This document defines additional conventions for Sentinel's backend API.
These standards extend the core coding standards.

---

## Frontend Separation

Sentinel is an **API-only backend**.

-   The frontend is a separate Vue/Nuxt 4 application
-   The backend does not serve Blade views
-   All data responses use JSON
-   OAuth callbacks redirect to the frontend URL

---

## Configuration

### Frontend URL

The frontend URL is configured via environment variable:

```env
FRONTEND_URL=http://localhost:3000
```

Access via config:

```php
config('app.frontend_url')
```

---

## API Responses

### Standard Response Structure

All API responses follow this structure:

```json
{
    "data": {},
    "message": "Optional success message"
}
```

For collections:

```json
{
    "data": []
}
```

---

### API Resources

All model responses MUST use Laravel API Resources.

Resources live in `App\Http\Resources`.

Example:

```php
return response()->json([
    'data' => new WorkspaceResource($workspace),
]);
```

---

### Paginated Responses

For paginated data, we preserve Laravel's default pagination structure while wrapping items in resources.

Pattern:

```php
$paginator = Model::query()->paginate();

$paginator->setCollection(
    $paginator->getCollection()->map(
        fn ($item) => new ModelResource($item)
    )
);

return response()->json($paginator);
```

This preserves the standard Laravel pagination metadata:

```json
{
    "current_page": 1,
    "data": [],
    "first_page_url": "...",
    "from": 1,
    "last_page": 1,
    "last_page_url": "...",
    "links": [],
    "next_page_url": null,
    "path": "...",
    "per_page": 15,
    "prev_page_url": null,
    "to": 10,
    "total": 10
}
```

---

## HTTP Status Codes

Standard HTTP status codes for responses:

| Code | Usage                                    |
| ---- | ---------------------------------------- |
| 200  | Success                                  |
| 201  | Resource created                         |
| 401  | Authentication required                  |
| 403  | Forbidden (authorization failure)        |
| 404  | Resource not found                       |
| 409  | Conflict (e.g., duplicate, already done) |
| 410  | Gone (e.g., expired resource)            |
| 422  | Validation/business rule failure         |

---

## Authentication

### OAuth Flow

1. Frontend redirects user to `/auth/{provider}/redirect`
2. User authenticates with provider
3. Provider redirects to `/auth/{provider}/callback`
4. Backend creates/authenticates user
5. Backend generates Sanctum token
6. Backend redirects to `{FRONTEND_URL}/auth/callback?token={token}`
7. Frontend stores token for subsequent API requests

---

### API Authentication

All authenticated API requests require:

```
Authorization: Bearer {token}
```

Token is obtained from the OAuth callback redirect.

---

## Route Structure

### Web Routes (routes/web.php)

OAuth routes only (require browser redirects):

-   `GET /auth/{provider}/redirect`
-   `GET /auth/{provider}/callback`

### API Routes (routes/api.php)

All other routes with `/api` prefix:

-   Authentication: `/api/user`, `/api/logout`
-   Workspaces: `/api/workspaces/*`
-   Members: `/api/workspaces/{workspace}/members/*`
-   Invitations: `/api/workspaces/{workspace}/invitations/*`

---

## Error Responses

Error responses follow this structure:

```json
{
    "message": "Human-readable error message"
}
```

For validation errors (422):

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field": ["Error message"]
    }
}
```

---

This document defines Sentinel's API conventions.
