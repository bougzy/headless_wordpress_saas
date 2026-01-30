# Authentication Flow

This document explains how authentication works across the Jenga platform, spanning the WordPress backend, Next.js frontend, and browser.

---

## Overview

Jenga uses **JWT (JSON Web Tokens)** for stateless authentication between the Next.js frontend and WordPress backend. Tokens are stored in **httpOnly cookies** — never exposed to client-side JavaScript.

**Key decisions:**
- Short-lived access tokens (1 hour) + long-lived refresh tokens (7 days)
- Signed with HMAC-SHA256 (shared secret between WordPress and Next.js)
- The Next.js API route layer acts as a Backend-for-Frontend (BFF) proxy

---

## Token Types

| Token | Lifetime | Purpose | Storage |
|-------|----------|---------|---------|
| Access Token | 1 hour | Authenticate API requests | httpOnly cookie (`jenga_access_token`) |
| Refresh Token | 7 days | Obtain new access tokens | httpOnly cookie (`jenga_refresh_token`) |

---

## Login Flow

```
Browser                    Next.js (BFF)              WordPress
  |                           |                          |
  |  POST /api/auth           |                          |
  |  {action:"login",         |                          |
  |   email, password}        |                          |
  |-------------------------->|                          |
  |                           |  POST /jenga/v1/auth/login
  |                           |  {email, password}       |
  |                           |------------------------->|
  |                           |                          |
  |                           |  Validate credentials    |
  |                           |  Generate JWT pair       |
  |                           |                          |
  |                           |  {user, tokens}          |
  |                           |<-------------------------|
  |                           |                          |
  |  Set httpOnly cookies     |                          |
  |  (access_token,           |                          |
  |   refresh_token)          |                          |
  |  Return {user}            |                          |
  |<--------------------------|                          |
  |                           |                          |
  |  Redirect to /dashboard   |                          |
```

1. User submits login form (client component)
2. Form POSTs to `/api/auth` (Next.js API route)
3. Next.js proxies the request to WordPress `/jenga/v1/auth/login`
4. WordPress validates credentials and returns JWT tokens
5. Next.js sets tokens as httpOnly cookies (not readable by JS)
6. Browser receives the user object (no tokens exposed)

---

## Registration Flow

Identical to login, but calls `/jenga/v1/auth/register` instead. WordPress:
1. Validates email format and uniqueness
2. Enforces minimum password length (8 characters)
3. Creates user with `jenga_free_member` role
4. Returns JWT tokens

---

## Authenticated Request Flow

```
Browser                    Next.js                    WordPress
  |                           |                          |
  |  Page request             |                          |
  |  (cookies sent auto)      |                          |
  |-------------------------->|                          |
  |                           |                          |
  |                    Server component reads            |
  |                    cookie via cookies()              |
  |                           |                          |
  |                           |  GET /jenga/v1/auth/me   |
  |                           |  Authorization: Bearer X |
  |                           |------------------------->|
  |                           |                          |
  |                           |  Validate JWT            |
  |                           |  Return user data        |
  |                           |<-------------------------|
  |                           |                          |
  |  Rendered page with       |                          |
  |  user-specific content    |                          |
  |<--------------------------|                          |
```

For server components:
- `getCurrentUser()` reads the access token from cookies
- Calls WordPress `/auth/me` with the token
- Returns the user object or `null`

For API route proxying (checkout, portal, cancel):
- `getAccessToken()` extracts the token from cookies
- Passes it through to WordPress endpoints

---

## Token Refresh Flow

```
Browser                    Next.js                    WordPress
  |                           |                          |
  |  Request with expired     |                          |
  |  access token             |                          |
  |-------------------------->|                          |
  |                           |                          |
  |                    getCurrentUser() fails            |
  |                    (access token expired)            |
  |                           |                          |
  |  Client detects 401       |                          |
  |  POST /api/auth           |                          |
  |  {action:"refresh"}       |                          |
  |-------------------------->|                          |
  |                           |  POST /jenga/v1/auth/refresh
  |                           |  {refresh_token}         |
  |                           |------------------------->|
  |                           |                          |
  |                           |  Validate refresh JWT    |
  |                           |  Issue new token pair    |
  |                           |<-------------------------|
  |                           |                          |
  |  Update httpOnly cookies  |                          |
  |<--------------------------|                          |
```

---

## Route Protection

### Middleware (Edge Runtime)

The Next.js middleware runs at the edge before any page renders:

```typescript
// Protected routes: /dashboard, /settings
// If no access_token cookie exists → redirect to /login
// If on /login with access_token → redirect to /dashboard
```

This is a fast, lightweight check (cookie existence only — no JWT validation at the edge).

### Server Component Guard

Dashboard layout performs a full auth check:

```typescript
const user = await getCurrentUser();
if (!user) redirect("/login?redirect=/dashboard");
```

This validates the JWT against WordPress, catching expired tokens that the edge middleware would miss.

### Content Gating

Content access is checked server-side:

```
1. GET /content/{slug} with optional Bearer token
2. WordPress checks content tier vs user tier
3. Returns body only if user tier >= content tier
4. Frontend renders gate UI if has_access is false
```

---

## Logout Flow

```
Browser                    Next.js
  |                           |
  |  POST /api/auth           |
  |  {action:"logout"}        |
  |-------------------------->|
  |                           |
  |  Delete httpOnly cookies  |
  |  Redirect to /            |
  |<--------------------------|
```

Logout is frontend-only — we delete the cookies. JWTs are stateless, so there is no server-side session to invalidate. The access token will naturally expire.

---

## Security Considerations

1. **httpOnly cookies**: Tokens cannot be read by JavaScript, preventing XSS token theft
2. **SameSite=Lax**: Cookies are not sent on cross-origin requests, mitigating CSRF
3. **Secure flag**: Cookies are only sent over HTTPS in production
4. **Short access token lifetime**: Limits the window of compromise
5. **No tokens in localStorage**: Eliminates the most common JWT vulnerability
6. **BFF proxy pattern**: WordPress credentials are never exposed to the browser
7. **Rate limiting**: Prevents brute force attacks on login/register endpoints
