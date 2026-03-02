# Token

## Overview

Token endpoint for OAuth 2.0 token exchange. Applications use this endpoint to exchange authorization codes for access tokens, or to refresh expired access tokens.

## Endpoint
```
POST https://api-chat.wokki20.nl/token
```

## Grant Types

This endpoint supports two grant types:
- `authorization_code` - Exchange authorization code for tokens
- `refresh_token` - Refresh an expired access token

---

## Scopes

Scopes control what data your application can access. Request only the scopes your application actually needs.

| Scope | Description |
|-------|-------------|
| `user:read` | Alias that grants both `user:read:profile` and `user:read:email` |
| `user:read:profile` | Read the authenticated user's profile information |
| `user:read:email` | Read the authenticated user's email address |
| `servers:read` | Read servers the authenticated user is a member of |
| `contacts:read` | Read the authenticated user's contacts |

> **Note:** Requesting `user:read` is equivalent to requesting `user:read:profile user:read:email`. The token response will always return the expanded individual scopes.

---

## Authorization Code Grant

Exchange an authorization code for access and refresh tokens.

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `grant_type` | string | Yes | Must be `authorization_code` |
| `code` | string | Yes | Authorization code from authorize endpoint |
| `redirect_uri` | string | Yes | Same redirect URI used in authorization request |
| `client_id` | string | Yes | Your OAuth client identifier |
| `client_secret` | string | Yes | Your OAuth client secret |

### Example Request
```bash
curl -X POST https://api-chat.wokki20.nl/token \
  -d grant_type=authorization_code \
  -d code=a1b2c3d4e5f6... \
  -d redirect_uri=https://example.com/callback \
  -d client_id=your_client_id \
  -d client_secret=your_client_secret
```

### Success Response

**Status:** `200 OK`
```json
{
  "access_token": "64_char_hex_token",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "64_char_hex_token",
  "scope": "user:read:profile user:read:email"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `access_token` | string | Access token for API requests (64 hex chars) |
| `token_type` | string | Always `Bearer` |
| `expires_in` | integer | Seconds until access token expires (3600 = 1 hour) |
| `refresh_token` | string | Refresh token to obtain new access tokens (64 hex chars) |
| `scope` | string | Granted scopes, space-separated and fully expanded |

### Error Responses

| Status | Error Code | Description |
|--------|------------|-------------|
| 400 | `invalid_request` | Missing required parameters |
| 401 | `invalid_client` | Invalid client credentials |
| 400 | `invalid_grant` | Invalid, expired, or used authorization code |
| 400 | `invalid_grant` | Redirect URI mismatch |
| 400 | `invalid_scope` | One or more requested scopes are invalid |

---

## Refresh Token Grant

Exchange a refresh token for new access and refresh tokens.

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `grant_type` | string | Yes | Must be `refresh_token` |
| `refresh_token` | string | Yes | Valid refresh token |
| `client_id` | string | Yes | Your OAuth client identifier |
| `client_secret` | string | Yes | Your OAuth client secret |

### Example Request
```bash
curl -X POST https://api-chat.wokki20.nl/token \
  -d grant_type=refresh_token \
  -d refresh_token=x1y2z3... \
  -d client_id=your_client_id \
  -d client_secret=your_client_secret
```

### Success Response

**Status:** `200 OK`
```json
{
  "access_token": "new_64_char_hex_token",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "new_64_char_hex_token",
  "scope": "user:read:profile user:read:email"
}
```

The response format is identical to the authorization code grant. Both tokens are rotated (old tokens invalidated).

### Error Responses

| Status | Error Code | Description |
|--------|------------|-------------|
| 400 | `invalid_request` | Missing required parameters |
| 401 | `invalid_client` | Invalid client credentials |
| 400 | `invalid_grant` | Invalid or expired refresh token |

---

## General Error Responses

All errors follow this format:
```json
{
  "error": "error_code",
  "error_description": "Human readable description"
}
```

| Status | Error Code | Description |
|--------|------------|-------------|
| 405 | `invalid_request` | Method not POST |
| 400 | `unsupported_grant_type` | Grant type not supported |

---

## Token Lifetimes

| Token Type | Lifetime |
|------------|----------|
| Access Token | 1 hour (3600 seconds) |
| Refresh Token | 30 days (2,592,000 seconds) |
| Authorization Code | 10 minutes (600 seconds) |

---

## Security Notes

- **Client Secret Protection:** Never expose `client_secret` in client-side code
- **HTTPS Required:** All token requests must use HTTPS
- **Token Storage:** Store tokens securely (encrypted storage, secure cookies)
- **Token Rotation:** Refresh tokens are rotated on each refresh (old token invalidated)
- **Single Use Codes:** Authorization codes can only be used once
- **Timing-Safe Comparison:** Client secret validated with `hash_equals()`

---

## Complete OAuth 2.0 Flow Example
```bash
# Step 1: Get authorization code (user redirected to authorize endpoint)
# User approves, receives code via redirect

# Step 2: Exchange code for tokens
curl -X POST https://api-chat.wokki20.nl/token \
  -d grant_type=authorization_code \
  -d code=abc123... \
  -d redirect_uri=https://example.com/callback \
  -d client_id=my_client \
  -d client_secret=my_secret

# Response:
# {
#   "access_token": "xyz789...",
#   "refresh_token": "def456...",
#   "expires_in": 3600,
#   "scope": "user:read:profile user:read:email"
# }

# Step 3: Use access token for API requests
curl https://api-chat.wokki20.nl/some_endpoint \
  -H "Authorization: Bearer xyz789..."

# Step 4: When access token expires, refresh it
curl -X POST https://api-chat.wokki20.nl/token \
  -d grant_type=refresh_token \
  -d refresh_token=def456... \
  -d client_id=my_client \
  -d client_secret=my_secret

# Receive new access_token and refresh_token
```

---

## Implementation Notes

- Authorization codes are stored as SHA-256 hashes
- Tokens are generated using cryptographically secure random bytes
- All database queries use prepared statements
- Old refresh tokens are deleted when refreshing (token rotation)
- Client authentication is required for all token requests
- Scope definitions are centralised in `allowed_scopes.php`, shared with the authorize endpoint