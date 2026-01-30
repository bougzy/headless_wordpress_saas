# API Reference

Base URL: `{WORDPRESS_URL}/wp-json/jenga/v1`

All endpoints return JSON. Authenticated endpoints require a `Bearer` token in the `Authorization` header.

---

## Authentication

### POST /auth/login

Authenticate with email and password. Returns user profile and JWT token pair.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "securepassword"
}
```

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "display_name": "John Doe",
    "avatar": "https://gravatar.com/...",
    "roles": ["jenga_pro_member"],
    "tier": 1,
    "subscription": { ... },
    "created_at": "2024-01-15 10:30:00"
  },
  "tokens": {
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "expires_in": 3600,
    "token_type": "Bearer"
  }
}
```

**Errors:**
| Status | Code | Description |
|--------|------|-------------|
| 401 | `jenga_auth_failed` | Invalid email or password |

---

### POST /auth/register

Create a new user account. Assigns the `jenga_free_member` role by default.

**Request:**
```json
{
  "email": "newuser@example.com",
  "password": "min8chars",
  "first_name": "Jane",
  "last_name": "Doe"
}
```

**Response (201):** Same structure as login.

**Errors:**
| Status | Code | Description |
|--------|------|-------------|
| 400 | `jenga_invalid_email` | Invalid email format |
| 400 | `jenga_weak_password` | Password under 8 characters |
| 409 | `jenga_email_exists` | Email already registered |

---

### POST /auth/refresh

Exchange a refresh token for a new token pair.

**Request:**
```json
{
  "refresh_token": "eyJ..."
}
```

**Response (200):**
```json
{
  "tokens": {
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "expires_in": 3600,
    "token_type": "Bearer"
  }
}
```

---

### GET /auth/me

Get the authenticated user's profile. Requires Bearer token.

**Headers:** `Authorization: Bearer {access_token}`

**Response (200):**
```json
{
  "user": { ... }
}
```

---

## Plans

### GET /plans

List all active subscription plans, sorted by tier.

**Response (200):**
```json
{
  "data": [
    {
      "id": 10,
      "name": "Free",
      "description": "Access to all free content.",
      "slug": "free",
      "price": 0,
      "stripe_price": "",
      "features": ["Access free articles", "Community access"],
      "tier": 0,
      "active": true
    },
    {
      "id": 11,
      "name": "Pro",
      "description": "Unlock Pro-tier content.",
      "slug": "pro",
      "price": 990,
      "stripe_price": "price_xxx",
      "features": ["Everything in Free", "Pro articles", "Monthly digest"],
      "tier": 1,
      "active": true
    }
  ],
  "total": 3
}
```

Note: `price` is in cents (990 = $9.90).

### GET /plans/{id}

Get a single plan by ID.

---

## Content

### GET /content

Paginated list of published content. Returns metadata only (no body).

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | int | 1 | Page number |
| `per_page` | int | 12 | Items per page (max 50) |
| `topic` | string | - | Filter by topic slug |
| `tier` | int | - | Filter by tier (0, 1, 2) |
| `search` | string | - | Full-text search |

**Response (200):**
```json
{
  "data": [
    {
      "id": 100,
      "title": "Getting Started with Headless WordPress",
      "slug": "getting-started-headless-wp",
      "excerpt": "Learn how to use WordPress as a headless CMS...",
      "tier": 0,
      "tier_label": "Free",
      "read_time": 8,
      "topics": [{ "id": 5, "name": "WordPress", "slug": "wordpress" }],
      "featured_image": "https://...",
      "author": {
        "id": 1,
        "name": "John Doe",
        "avatar": "https://..."
      },
      "published_at": "2024-03-15 12:00:00",
      "updated_at": "2024-03-16 09:30:00"
    }
  ],
  "meta": {
    "total": 45,
    "pages": 4,
    "current_page": 1,
    "per_page": 12
  }
}
```

### GET /content/{slug}

Get a single content item by slug. Supports optional authentication.

- **Without token:** Returns metadata + excerpt. `has_access` reflects free tier.
- **With token:** Returns full body if user's tier meets the content's tier requirement.

**Headers (optional):** `Authorization: Bearer {access_token}`

**Response (200):**
```json
{
  "data": {
    "id": 100,
    "title": "Advanced WordPress Patterns",
    "slug": "advanced-wp-patterns",
    "excerpt": "...",
    "tier": 1,
    "tier_label": "Pro",
    "read_time": 12,
    "topics": [...],
    "featured_image": "https://...",
    "author": { ... },
    "published_at": "...",
    "updated_at": "...",
    "has_access": true,
    "body": "<p>Full HTML content...</p>"
  }
}
```

When `has_access` is `false`, `body` is omitted and `upgrade_message` is included.

---

## Subscriptions

All subscription endpoints require authentication.

### GET /subscriptions/current

Get the current user's active subscription.

**Response (200):**
```json
{
  "data": {
    "id": 50,
    "user_id": 1,
    "plan_id": 11,
    "plan": { ... },
    "stripe_id": "sub_xxx",
    "status": "active",
    "current_period_end": 1711929600,
    "created_at": 1709251200
  }
}
```

Returns `"data": null` if no active subscription.

### POST /subscriptions/checkout

Create a Stripe Checkout session for a plan.

**Request:**
```json
{
  "plan_id": 11
}
```

**Response (200):**
```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/...",
  "session_id": "cs_xxx"
}
```

### POST /subscriptions/portal

Create a Stripe Billing Portal session for subscription management.

**Response (200):**
```json
{
  "portal_url": "https://billing.stripe.com/p/session/..."
}
```

### POST /subscriptions/cancel

Cancel the current subscription.

**Response (200):**
```json
{
  "message": "Subscription cancelled successfully."
}
```

---

## Webhooks

### POST /webhooks/stripe

Receives Stripe webhook events. Verified by Stripe signature header.

**Handled Events:**
| Event | Action |
|-------|--------|
| `checkout.session.completed` | Creates subscription record, assigns user role |
| `customer.subscription.updated` | Updates subscription status and period |
| `customer.subscription.deleted` | Expires subscription, downgrades user |
| `invoice.payment_failed` | Marks subscription as past_due |

### POST /webhooks/revalidate

Trigger Next.js ISR revalidation from WordPress.

**Request:**
```json
{
  "secret": "your-revalidation-secret",
  "paths": ["/content", "/content/my-article"]
}
```

---

## Rate Limiting

All `/jenga/v1` endpoints (except webhooks) are rate-limited.

**Default:** 60 requests per 60-second window per IP.

**Headers included in responses:**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 55
X-RateLimit-Reset: 1711929660
```

**429 Response** when exceeded:
```json
{
  "code": "jenga_rate_limited",
  "message": "Rate limit exceeded. Please try again later.",
  "data": { "status": 429 }
}
```

---

## Error Format

All errors follow this structure:

```json
{
  "code": "jenga_error_code",
  "message": "Human-readable error message.",
  "data": {
    "status": 400
  }
}
```
