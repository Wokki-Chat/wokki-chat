# Authorization

## Overview

Authorization endpoint for OAuth 2.0 authorization code flow. External applications redirect users here to request authorization.

## Endpoint
```
GET https://api-chat.wokki20.nl/authorize
```

## Request Parameters

### Required

| Parameter | Type | Description |
|-----------|------|-------------|
| `client_id` | string | Your OAuth client identifier |
| `redirect_uri` | string | Registered callback URI |

### Optional

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `response_type` | string | `code` | Must be `code` (only supported type) |
| `scopes` | string | - | Space-separated permission scopes |
| `state` | string | - | Random value for CSRF protection (recommended) |

## Scopes

Scopes control what data your application can access on behalf of the user. Request only the scopes your application actually needs. Invalid scopes are rejected before the user sees the authorization page.

| Scope | Description |
|-------|-------------|
| `user:read` | Alias that grants both `user:read:profile` and `user:read:email` |
| `user:read:profile` | Read the authenticated user's profile information |
| `user:read:email` | Read the authenticated user's email address |
| `servers:read` | Read servers the authenticated user is a member of |
| `contacts:read` | Read the authenticated user's contacts |

> **Note:** Requesting `user:read` is equivalent to requesting `user:read:profile user:read:email`. The token response will always return the expanded individual scopes.

### Example Scope Strings

```
scopes=user:read
scopes=user:read:profile user:read:email servers:read
scopes=contacts:read
```

## Example Request
```
GET https://api-chat.wokki20.nl/authorize?client_id=abc123&redirect_uri=https://example.com/callback&state=random123&scopes=user:read%20servers:read
```

## Response Flow

### Success Flow
1. User sees authorization page
2. User approves request
3. Redirect to callback with authorization code:
```
HTTP/1.1 302 Found
Location: https://example.com/callback?code={64_char_code}&state=random123
```

### Denial Flow
User denies request, redirect with error:
```
HTTP/1.1 302 Found
Location: https://example.com/callback?error=access_denied&state=random123
```

## Error Responses

| Status | Condition | Response |
|--------|-----------|----------|
| 400 | Missing `client_id` or `redirect_uri` | `Missing client_id or redirect_uri.` |
| 400 | `response_type` is not `code` | `Unsupported response_type.` |
| 400 | One or more invalid scopes requested | `One or more requested scopes are invalid.` |
| 400 | Unknown, inactive, or revoked client | `Unknown or inactive client.` |
| 400 | `redirect_uri` not registered | `Redirect URI is not registered for this client.` |
| 405 | Method not GET or POST | No body |

## Authorization Code

- **Format:** 64 hexadecimal characters
- **Lifetime:** 10 minutes
- **Usage:** Single-use only (exchange for access token at token endpoint)
- **Storage:** Stored as SHA-256 hash

## Security Notes

- `redirect_uri` must exactly match a pre-registered URI
- `state` parameter strongly recommended to prevent CSRF
- Authorization codes expire after 10 minutes
- Codes are cryptographically random (32 bytes)
- Scope validation happens before the user sees the authorization page

## Client Registration Requirements

Before using this endpoint, your OAuth client must be registered with:
1. `client_id` assigned
2. One or more `redirect_uri` values registered
3. Client marked as active
4. Not revoked

## Authentication

User authentication is handled via session cookie (`access_token`). If user is not authenticated, they will see option to log in before approving.