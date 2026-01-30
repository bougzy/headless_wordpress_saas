# Jenga Headless WordPress SaaS -- Architecture Documentation

This document describes the end-to-end architecture of the Jenga platform: a
headless WordPress content-subscription SaaS with a Next.js frontend, Stripe
payments, and tier-based content gating.

---

## Table of Contents

1. [System Architecture](#1-system-architecture)
2. [Data Flow Diagrams](#2-data-flow-diagrams)
3. [WordPress Backend Architecture](#3-wordpress-backend-architecture)
4. [Next.js Frontend Architecture](#4-nextjs-frontend-architecture)
5. [Rendering Strategy](#5-rendering-strategy)
6. [Security Architecture](#6-security-architecture)
7. [Caching Strategy](#7-caching-strategy)

---

## 1. System Architecture

### High-Level Component Diagram

```
+------------------------------------------------------------------+
|                          BROWSER                                  |
|  (React client components, Stripe.js, cookie-based auth)         |
+----------------------------+-------------------------------------+
                             |
                             | HTTPS
                             v
+------------------------------------------------------------------+
|                     CDN / EDGE (Vercel)                           |
|                                                                   |
|  +------------------------------------------------------------+  |
|  |                 NEXT.JS APP (Vercel)                        |  |
|  |                                                              |  |
|  |  +------------------+  +------------------+                  |  |
|  |  |  Edge Middleware  |  | App Router (SSR) |                  |  |
|  |  | (route protection)|  | (ISR, static)    |                  |  |
|  |  +------------------+  +------------------+                  |  |
|  |                                                              |  |
|  |  +------------------+  +------------------+                  |  |
|  |  |  API Routes      |  | Server           |                  |  |
|  |  |  (BFF layer)     |  | Components       |                  |  |
|  |  |  /api/auth       |  | (data fetching)  |                  |  |
|  |  |  /api/revalidate |  |                  |                  |  |
|  |  |  /api/stripe/*   |  |                  |                  |  |
|  |  +--------+---------+  +--------+---------+                  |  |
|  |           |                      |                            |  |
|  +-----------|----------------------|----------------------------+  |
|              |                      |                              |
+--------------|---------+------------|------------------------------+
               |         |            |
               |         |            | REST API (HTTPS)
               |         |            v
               |         |  +------------------------------------------+
               |         |  |        WORDPRESS REST API                 |
               |         |  |        (Headless CMS + Business Logic)    |
               |         |  |                                          |
               |         |  |  +----------------+  +-----------------+ |
               |         |  |  | jenga/v1 API   |  | Auth Middleware | |
               |         |  |  | Controllers    |  | (JWT + CORS)   | |
               |         |  |  +-------+--------+  +-----------------+ |
               |         |  |          |                               |
               |         |  |  +-------+--------+  +-----------------+ |
               |         |  |  | Custom Post    |  | Rate Limiter    | |
               |         |  |  | Types + Roles  |  | (Transient)     | |
               |         |  |  +-------+--------+  +-----------------+ |
               |         |  |          |                               |
               |         |  +----------|-------------------------------+
               |         |             |
               |         |             v
               |         |  +---------------------+
               |         |  |       MySQL          |
               |         |  |  (wp_posts, wp_meta, |
               |         |  |   wp_users, etc.)    |
               |         |  +---------------------+
               |         |
               |         v
        +------+-------------------+
        |        STRIPE            |
        |  - Checkout Sessions     |
        |  - Subscriptions         |
        |  - Billing Portal        |
        |  - Webhook Events        |
        +--------------------------+
```

### Component Responsibilities

| Component          | Responsibility                                                  |
|--------------------|-----------------------------------------------------------------|
| **Browser**        | Renders React components, handles client interactions, Stripe.js |
| **CDN / Edge**     | Serves static assets, caches ISR pages, runs Edge Middleware     |
| **Next.js App**    | SSR, ISR, API routes (BFF), server components, middleware        |
| **WordPress**      | REST API, business logic, content management, JWT auth           |
| **MySQL**          | Persistent storage for posts, users, subscriptions, meta         |
| **Stripe**         | Payment processing, subscription lifecycle, webhooks             |

---

## 2. Data Flow Diagrams

### 2.1 Page Request Flow (ISR)

```
  Browser                  Vercel Edge/CDN              Next.js              WordPress API
    |                           |                         |                       |
    |  GET /content             |                         |                       |
    |-------------------------->|                         |                       |
    |                           |                         |                       |
    |                    [cache hit?]                      |                       |
    |                     /        \                       |                       |
    |                   yes         no                     |                       |
    |                   /            \                     |                       |
    |    <-- cached HTML              |                    |                       |
    |                                 |  render page       |                       |
    |                                 |------ ------------>|                       |
    |                                 |                    |  GET /jenga/v1/content|
    |                                 |                    |---------------------->|
    |                                 |                    |                       |
    |                                 |                    |    JSON response      |
    |                                 |                    |<----------------------|
    |                                 |                    |                       |
    |                                 |    HTML + headers  |                       |
    |                                 |<-------------------|                       |
    |                                 |                    |                       |
    |                  [store in cache                     |                       |
    |                   with revalidate                    |                       |
    |                   TTL: 60s]                          |                       |
    |                                 |                    |                       |
    |    <-- fresh HTML               |                    |                       |
    |                                 |                    |                       |
```

After the TTL expires, the next visitor gets the stale page instantly while
Next.js regenerates in the background (stale-while-revalidate pattern).

### 2.2 Authentication Flow (JWT)

```
  Browser              Next.js /api/auth         WordPress /jenga/v1/auth
    |                        |                            |
    |  POST {action:"login"  |                            |
    |    email, password}    |                            |
    |----------------------->|                            |
    |                        |  POST /auth/login          |
    |                        |  {email, password}         |
    |                        |--------------------------->|
    |                        |                            |
    |                        |                  [wp_authenticate()]
    |                        |                            |
    |                        |    {user, tokens:          |
    |                        |      {access_token,        |
    |                        |       refresh_token,       |
    |                        |       expires_in}}         |
    |                        |<---------------------------|
    |                        |                            |
    |               [setAuthCookies()]                    |
    |               Set-Cookie: jenga_access_token        |
    |                 (httpOnly, secure, lax,              |
    |                  maxAge=3600)                        |
    |               Set-Cookie: jenga_refresh_token       |
    |                 (httpOnly, secure, lax,              |
    |                  maxAge=604800)                      |
    |                        |                            |
    |  <-- {user} + cookies  |                            |
    |                        |                            |
    |                                                     |
    |  --- subsequent requests include cookies --->       |
    |                                                     |
    |  [On token expiry: POST {action:"refresh"}]         |
    |  [reads jenga_refresh_token cookie]                 |
    |  [calls /auth/refresh -> new token pair]            |
    |  [sets new cookies, transparent to user]            |
```

Token lifecycle summary:
- **Access token**: 1 hour (3600s), carried in httpOnly cookie
- **Refresh token**: 7 days (604800s), carried in httpOnly cookie
- **Algorithm**: HMAC-SHA256 (HS256) via `firebase/php-jwt`

### 2.3 Payment / Checkout Flow (Stripe)

```
  Browser             Next.js /api/auth         WordPress API            Stripe
    |                       |                        |                     |
    | POST {action:         |                        |                     |
    |  "checkout",          |                        |                     |
    |  plan_id: 42}         |                        |                     |
    |---------------------->|                        |                     |
    |                       | [reads access_token    |                     |
    |                       |  from cookie]          |                     |
    |                       |                        |                     |
    |                       | POST /subscriptions/   |                     |
    |                       |   checkout              |                     |
    |                       | Authorization: Bearer   |                     |
    |                       |----------------------->|                     |
    |                       |                        |                     |
    |                       |                        | Checkout.Session    |
    |                       |                        |   .create({         |
    |                       |                        |     mode:           |
    |                       |                        |      "subscription" |
    |                       |                        |     price, user,    |
    |                       |                        |     success_url,    |
    |                       |                        |     cancel_url})    |
    |                       |                        |----- ------------->|
    |                       |                        |                     |
    |                       |                        |  {session.url,      |
    |                       |                        |   session.id}       |
    |                       |                        |<--------------------|
    |                       |                        |                     |
    |                       | {checkout_url}         |                     |
    |                       |<-----------------------|                     |
    |                       |                        |                     |
    | <-- {checkout_url}    |                        |                     |
    |                       |                        |                     |
    | redirect to           |                        |                     |
    | checkout_url ---------|------------------------|-------------------->|
    |                       |                        |                     |
    |                 [user completes payment on Stripe Checkout]          |
    |                       |                        |                     |
    | <-- redirect to       |                        |                     |
    |  /dashboard?checkout  |                        |                     |
    |  =success             |                        |                     |
    |                       |                        |                     |
    |                       |                        |   POST webhook:     |
    |                       |                        |   checkout.session  |
    |                       |                        |   .completed        |
    |                       |                        |<--------------------|
    |                       |                        |                     |
    |                       |               [StripeHandler::               |
    |                       |                on_checkout_completed()       |
    |                       |                - create Subscription CPT     |
    |                       |                - assign user role/tier       |
    |                       |                - trigger revalidation]       |
    |                       |                        |                     |
```

### 2.4 Content Access / Gating Flow

```
  Browser             Next.js Server             WordPress API
    |                       |                          |
    | GET /content/my-post  |                          |
    |---------------------->|                          |
    |                       |                          |
    |              [reads access_token                 |
    |               cookie -- may be null]             |
    |                       |                          |
    |                       | GET /content/my-post     |
    |                       | Authorization: Bearer    |
    |                       |  (if token exists)       |
    |                       |------------------------->|
    |                       |                          |
    |                       |                 [ContentController::show()]
    |                       |                          |
    |                       |                 [optional_auth middleware:
    |                       |                  - if token, set current user
    |                       |                  - if no token, anonymous]
    |                       |                          |
    |                       |                 [Content::user_can_access()
    |                       |                  - get content tier (0/1/2)
    |                       |                  - if tier=0: allow all
    |                       |                  - get user subscription tier
    |                       |                  - compare: user_tier >= tier]
    |                       |                          |
    |                       |                 [Content::to_array($post,
    |                       |                   $full=$has_access)
    |                       |                  - always: metadata, excerpt
    |                       |                  - if $full: include body
    |                       |                  - if !$full: upgrade_message]
    |                       |                          |
    |                       | <-- JSON {data, has_access}
    |                       |                          |
    |  [render page:]       |                          |
    |  has_access=true:     |                          |
    |    full article body  |                          |
    |  has_access=false:    |                          |
    |    excerpt + paywall  |                          |
    |    + upgrade CTA      |                          |
    |                       |                          |
    | <-- HTML              |                          |
```

Tier hierarchy:

| Tier | Label   | Access                     |
|------|---------|----------------------------|
| 0    | Free    | Free content only          |
| 1    | Pro     | Free + Pro content         |
| 2    | Premium | Free + Pro + Premium       |

### 2.5 Webhook Revalidation Flow

```
  WordPress Admin          RevalidationDispatcher       Next.js /api/revalidate
       |                           |                            |
       | [save_post or             |                            |
       |  delete_post fires]       |                            |
       |-------------------------->|                            |
       |                           |                            |
       |                  [get_paths_for_post()                 |
       |                   jenga_content -> ["/content",        |
       |                                     "/content/{slug}"] |
       |                   jenga_plan    -> ["/pricing"]]       |
       |                           |                            |
       |                           | POST /api/revalidate       |
       |                           | {secret, paths}            |
       |                           | (non-blocking)             |
       |                           |--------------------------->|
       |                           |                            |
       |                           |               [validate secret]
       |                           |                            |
       |                           |               [for each path:
       |                           |                revalidatePath(p)]
       |                           |                            |
       |                           |   {revalidated: true,      |
       |                           |    paths: [...]}           |
       |                           |<---------------------------|
       |                           |                            |
```

Additionally, the `StripeHandler` triggers revalidation after payment events:
- `checkout.session.completed` revalidates `/dashboard` and `/content`
- `customer.subscription.deleted` revalidates `/dashboard`

The WordPress dispatcher uses `blocking: false` in `wp_remote_post` so content
editors are not blocked waiting for the Next.js revalidation response.

---

## 3. WordPress Backend Architecture

### 3.1 Plugin Structure

```
wordpress/plugins/jenga-saas-core/
|-- jenga-saas-core.php          # Plugin bootstrap, hooks, autoloader
|-- composer.json                # PSR-4 autoload, dependencies
|-- config/
|   +-- settings.php             # Environment-based configuration
|-- src/
|   |-- Plugin.php               # Singleton orchestrator
|   |-- PostTypes/
|   |   |-- Plan.php             # Plan CPT + meta registration
|   |   |-- Subscription.php     # Subscription CPT + meta registration
|   |   +-- Content.php          # Content CPT + taxonomy + access gating
|   |-- Auth/
|   |   |-- JWT.php              # Token generation, validation, refresh
|   |   +-- Middleware.php       # REST permission callbacks
|   |-- Roles/
|   |   +-- RoleManager.php      # Custom roles + capabilities
|   |-- API/
|   |   |-- Middleware/
|   |   |   +-- RateLimiter.php  # Sliding window rate limiter
|   |   +-- V1/
|   |       |-- AuthController.php         # /auth/* endpoints
|   |       |-- PlanController.php         # /plans/* endpoints
|   |       |-- ContentController.php      # /content/* endpoints
|   |       |-- SubscriptionController.php # /subscriptions/* endpoints
|   |       +-- WebhookController.php      # /webhooks/* endpoints
|   |-- Payments/
|   |   +-- StripeHandler.php    # Stripe Checkout, Portal, webhooks
|   +-- Webhooks/
|       +-- RevalidationDispatcher.php  # ISR revalidation on content changes
+-- vendor/                      # Composer dependencies
```

**PSR-4 Namespace Mapping:**

```
Jenga\SaaS\  -->  src/
```

**Dependencies** (`composer.json`):

| Package              | Version | Purpose                    |
|----------------------|---------|----------------------------|
| `firebase/php-jwt`   | ^6.10   | JWT encode/decode (HS256)  |
| `stripe/stripe-php`  | ^13.0   | Stripe API client          |

**PHP Version**: >= 8.1 (strict types, match expressions, named arguments,
readonly properties, union types)

**WordPress Version**: >= 6.4

### 3.2 Plugin Initialization

```
plugins_loaded hook
    |
    v
jenga_saas_boot()
    |
    v
Plugin::get_instance()->init()
    |
    +-- register_post_types()    --> Plan, Subscription, Content
    +-- register_api()           --> Controllers + RateLimiter
    +-- register_hooks()         --> CORS headers + RevalidationDispatcher
```

### 3.3 Custom Post Types

#### Plan (`jenga_plan`)

Represents subscription tiers (Free, Pro, Premium).

| Meta Key                    | Type    | Description                            |
|-----------------------------|---------|----------------------------------------|
| `_jenga_plan_price`         | float   | Monthly price in cents                 |
| `_jenga_plan_stripe_price`  | string  | Stripe Price ID (e.g., `price_xxx`)    |
| `_jenga_plan_features`      | string  | JSON array of feature strings          |
| `_jenga_plan_tier`          | integer | Access tier level (0=free, 1=pro, 2=premium) |
| `_jenga_plan_active`        | boolean | Whether the plan is currently offered  |

- `public: false`, `show_ui: true` -- managed in wp-admin, not exposed via
  default REST.
- `show_in_rest: false` -- custom routes in `jenga/v1` namespace instead.

#### Subscription (`jenga_subscription`)

Tracks user subscriptions to plans. Links WordPress users to Stripe subscriptions.

| Meta Key                          | Type    | Description                        |
|-----------------------------------|---------|------------------------------------|
| `_jenga_sub_user_id`              | integer | WordPress user ID                  |
| `_jenga_sub_plan_id`              | integer | Plan post ID                       |
| `_jenga_sub_stripe_id`            | string  | Stripe Subscription ID             |
| `_jenga_sub_stripe_customer`      | string  | Stripe Customer ID                 |
| `_jenga_sub_status`               | string  | active, cancelled, past_due, trialing, expired |
| `_jenga_sub_current_period_end`   | integer | Unix timestamp of period end       |
| `_jenga_sub_created_at`           | integer | Unix timestamp of creation         |

Key static methods:
- `get_user_subscription(int $user_id)` -- finds active/trialing subscription
- `get_by_stripe_id(string $stripe_sub_id)` -- lookup by Stripe ID
- `get_user_tier(int $user_id)` -- returns the user's access tier (0/1/2)

#### Content (`jenga_content`)

Gated articles, courses, and resources. Supports a custom taxonomy `jenga_topic`.

| Meta Key                      | Type    | Description                            |
|-------------------------------|---------|----------------------------------------|
| `_jenga_content_tier`         | integer | Minimum tier required (0/1/2)          |
| `_jenga_content_excerpt`      | string  | Public teaser text (visible to all)    |
| `_jenga_content_read_time`    | integer | Estimated read time in minutes         |
| `_jenga_content_author_id`    | integer | WordPress user ID of the creator       |

Access gating logic (`Content::user_can_access`):
1. If `required_tier === 0`, allow all users.
2. If user has `manage_options` capability, allow (admin override).
3. Look up user's subscription tier via `Subscription::get_user_tier`.
4. Allow if `user_tier >= required_tier`.

### 3.4 Custom Roles and Capabilities

| Role Slug               | Display Name          | Capabilities                                      |
|--------------------------|-----------------------|---------------------------------------------------|
| `jenga_free_member`      | Jenga Free Member     | `read`, `jenga_read_free`                         |
| `jenga_pro_member`       | Jenga Pro Member      | `read`, `jenga_read_free`, `jenga_read_pro`       |
| `jenga_premium_member`   | Jenga Premium Member  | `read`, `jenga_read_free`, `jenga_read_pro`, `jenga_read_premium` |
| `jenga_creator`          | Jenga Creator         | All read caps + `edit_posts`, `publish_posts`, `upload_files`, `jenga_create_content` |
| `administrator`          | (built-in)            | All custom caps + `jenga_manage_platform`          |

Role assignment is automated:
- On registration: user gets `jenga_free_member`.
- On successful checkout: `RoleManager::assign_tier_role()` promotes the user.
- On cancellation/expiry: user is downgraded to `jenga_free_member` (tier 0).

### 3.5 REST API Endpoints

All endpoints are registered under the `jenga/v1` namespace.

#### Authentication

| Method | Endpoint             | Auth Required | Description                     |
|--------|----------------------|---------------|---------------------------------|
| POST   | `/auth/login`        | No            | Authenticate with email/password|
| POST   | `/auth/register`     | No            | Create new user account         |
| POST   | `/auth/refresh`      | No            | Refresh access token            |
| GET    | `/auth/me`           | Yes (JWT)     | Get current user profile        |

#### Plans

| Method | Endpoint             | Auth Required | Description                     |
|--------|----------------------|---------------|---------------------------------|
| GET    | `/plans`             | No            | List all active plans           |
| GET    | `/plans/{id}`        | No            | Get a single plan by ID         |

#### Content

| Method | Endpoint             | Auth Required | Description                     |
|--------|----------------------|---------------|---------------------------------|
| GET    | `/content`           | No            | List content (metadata only)    |
| GET    | `/content/{slug}`    | Optional      | Get single content (gated body) |

Query parameters for `/content`: `page`, `per_page`, `topic`, `tier`, `search`.

#### Subscriptions

| Method | Endpoint                    | Auth Required | Description                      |
|--------|-----------------------------|---------------|----------------------------------|
| GET    | `/subscriptions/current`    | Yes (JWT)     | Get current user's subscription  |
| POST   | `/subscriptions/checkout`   | Yes (JWT)     | Create Stripe Checkout session   |
| POST   | `/subscriptions/portal`     | Yes (JWT)     | Create Stripe Billing Portal     |
| POST   | `/subscriptions/cancel`     | Yes (JWT)     | Cancel active subscription       |

#### Webhooks

| Method | Endpoint                    | Auth Required | Description                       |
|--------|-----------------------------|---------------|-----------------------------------|
| POST   | `/webhooks/stripe`          | Stripe sig    | Handle Stripe webhook events      |
| POST   | `/webhooks/revalidate`      | Shared secret | Trigger Next.js ISR revalidation  |

### 3.6 Middleware Stack

Requests to `jenga/v1` routes pass through the following layers:

```
  Incoming Request
       |
       v
  1. WordPress core REST dispatch
       |
       v
  2. CORS Headers (Plugin::add_cors_headers)
     - Access-Control-Allow-Origin: JENGA_FRONTEND_URL
     - Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
     - Access-Control-Allow-Headers: Authorization, Content-Type,
                                      X-WP-Nonce, X-Jenga-Nonce
     - Access-Control-Allow-Credentials: true
     - Access-Control-Max-Age: 86400
       |
       v
  3. Rate Limiter (rest_pre_dispatch filter)
     - Sliding window counter per client IP
     - Default: 60 requests per 60-second window
     - Skips /webhooks/* routes
     - Returns 429 if exceeded
     - Sets headers: X-RateLimit-Limit, X-RateLimit-Remaining,
                     X-RateLimit-Reset
       |
       v
  4. Route-level permission_callback
     - __return_true          (public endpoints)
     - Middleware::require_auth  (authenticated endpoints)
     - Middleware::require_admin (admin endpoints)
     - Middleware::optional_auth (mixed endpoints like content detail)
       |
       v
  5. Controller method (business logic)
```

### 3.7 Stripe Webhook Event Handling

The `StripeHandler` processes these Stripe events:

| Event Type                          | Action                                              |
|-------------------------------------|-----------------------------------------------------|
| `checkout.session.completed`        | Create Subscription CPT, store Stripe IDs, assign role, revalidate |
| `customer.subscription.updated`     | Sync status (active/trialing/past_due/expired), update role        |
| `customer.subscription.deleted`     | Mark expired, downgrade user to free tier, revalidate              |
| `invoice.payment_failed`            | Mark subscription as `past_due`                                    |

Webhook signature is verified using `Stripe\Webhook::constructEvent()` with
`JENGA_STRIPE_WEBHOOK_SECRET`.

---

## 4. Next.js Frontend Architecture

### 4.1 Directory Structure

```
frontend/
|-- app/
|   |-- layout.tsx                    # Root layout
|   |-- page.tsx                      # Home page (ISR, async server component)
|   |-- (public)/                     # Public route group (no layout wrapper)
|   |   |-- pricing/page.tsx          # Pricing page (ISR 3600s)
|   |   |-- content/page.tsx          # Content list (ISR 60s)
|   |   |-- content/[slug]/page.tsx   # Content detail (ISR 300s / no-store)
|   |   +-- login/page.tsx            # Login page (static)
|   |-- (dashboard)/                  # Protected route group
|   |   |-- layout.tsx                # Auth guard layout
|   |   |-- dashboard/page.tsx        # Dashboard (SSR, no-store)
|   |   +-- settings/page.tsx         # Settings (SSR, no-store)
|   +-- api/
|       |-- auth/route.ts             # Auth BFF (login, register, logout, refresh, checkout, portal)
|       |-- revalidate/route.ts       # On-demand ISR revalidation endpoint
|       +-- stripe/webhook/route.ts   # Stripe webhook -> revalidation
|-- lib/
|   |-- constants.ts                  # Environment config, tier labels
|   |-- wordpress.ts                  # WordPress REST API client (WordPressClient class)
|   |-- auth.ts                       # Server-side auth utilities (cookie management)
|   +-- stripe.ts                     # Stripe.js client initialization
|-- components/
|   |-- ui/                           # Primitive UI components
|   |   |-- button.tsx
|   |   |-- input.tsx
|   |   |-- badge.tsx
|   |   +-- card.tsx
|   |-- layout/                       # Layout components
|   |   |-- header.tsx
|   |   +-- footer.tsx
|   +-- features/                     # Feature components
|       |-- content-card.tsx
|       |-- pricing-card.tsx
|       +-- login-form.tsx
|-- types/
|   +-- index.ts                      # TypeScript interfaces for all API types
|-- middleware.ts                      # Edge Middleware (route protection)
```

### 4.2 Route Groups

The App Router uses two route groups with distinct concerns:

#### `(public)` -- Public Routes

- `/pricing` -- Plan listing and comparison
- `/content` -- Content library with tier filters and pagination
- `/content/[slug]` -- Individual content (metadata always public, body gated)
- `/login` -- Authentication form (login + register tabs)

These routes render as async server components. They call `getCurrentUser()`
to optionally detect the logged-in user for header personalization but do not
require authentication.

#### `(dashboard)` -- Protected Routes

- `/dashboard` -- User overview, subscription status, access level
- `/settings` -- Account settings

Protected at two levels:
1. **Edge Middleware** (`middleware.ts`): checks for `jenga_access_token` cookie,
   redirects to `/login?redirect=...` if absent.
2. **Dashboard layout** (`(dashboard)/layout.tsx`): server-side guard calls
   `getCurrentUser()` and redirects if null (safety net).

### 4.3 Server vs Client Component Strategy

| Component Type       | Usage                                              | Examples                           |
|----------------------|----------------------------------------------------|------------------------------------|
| **Server Component** | Pages, layouts, data fetching, auth checks         | All `page.tsx`, `layout.tsx` files |
| **Client Component** | Interactive forms, Stripe.js, dynamic UI           | `login-form.tsx`, `pricing-card.tsx` (checkout button) |

Design principles:
- Default to server components for data fetching and rendering.
- Use client components (`"use client"`) only when browser APIs, event handlers,
  or React state are required.
- Pass server-fetched data as props to client components (server-to-client
  boundary).

### 4.4 API Routes as BFF (Backend-for-Frontend)

The Next.js API routes act as a Backend-for-Frontend layer between the browser
and WordPress:

```
  Browser  <-->  /api/auth  <-->  WordPress /jenga/v1/auth/*
  Browser  <-->  /api/auth  <-->  WordPress /jenga/v1/subscriptions/*
```

**Why a BFF layer?**
1. **Token security**: JWT tokens are stored in httpOnly cookies that JavaScript
   cannot access. The BFF reads cookies server-side and forwards the Bearer
   token to WordPress.
2. **Single endpoint**: The browser calls one `/api/auth` route with an
   `action` parameter. The BFF dispatches to the correct WordPress endpoint.
3. **Cookie management**: Setting and clearing httpOnly cookies can only happen
   in the server response, not in client-side JavaScript.

Supported actions via `POST /api/auth`:

| Action     | WordPress Endpoint              | Description                    |
|------------|---------------------------------|--------------------------------|
| `login`    | `/auth/login`                   | Authenticate, set cookies      |
| `register` | `/auth/register`                | Create account, set cookies    |
| `logout`   | (local only)                    | Clear cookies, redirect        |
| `refresh`  | `/auth/refresh`                 | Refresh tokens, update cookies |
| `checkout` | `/subscriptions/checkout`       | Create Stripe Checkout session |
| `portal`   | `/subscriptions/portal`         | Create Stripe Billing Portal   |

### 4.5 WordPress API Client

`lib/wordpress.ts` exports a `WordPressClient` class with:

- Type-safe methods for every API endpoint.
- Automatic Bearer token injection.
- Next.js cache control (`revalidate`, `tags`, `cache` options).
- Custom `ApiError` class with HTTP status and error code.
- Singleton instance: `export const wp = new WordPressClient(WP_API_URL)`.

### 4.6 Middleware (Edge)

`middleware.ts` runs at the Vercel Edge before any page render:

```typescript
const PROTECTED_ROUTES = ["/dashboard", "/settings"];
const AUTH_ROUTES = ["/login"];
```

- **Protected routes**: If no `jenga_access_token` cookie, redirect to
  `/login?redirect={pathname}`.
- **Auth routes**: If `jenga_access_token` cookie exists, redirect to
  `/dashboard` (prevents logged-in users from seeing the login page).

Matcher config: `["/dashboard/:path*", "/settings/:path*", "/login"]`

---

## 5. Rendering Strategy

| Route                   | Strategy           | Revalidation            | Cache Directive     | Reasoning                                      |
|-------------------------|--------------------|-------------------------|---------------------|-------------------------------------------------|
| `/` (Home)              | ISR                | 60s (content list call) | `revalidate: 60`   | Shows recent content; updates frequently        |
| `/content`              | ISR                | 60s + on-demand         | `revalidate: 60`, tag: `content` | New articles published regularly; webhook revalidation |
| `/content/[slug]`       | ISR (public) / SSR (auth) | 300s (public), no-store (authenticated) | Conditional | Public metadata cached; authenticated requests bypass cache for access gating |
| `/pricing`              | ISR                | 3600s + on-demand       | `revalidate: 3600`, tag: `plans` | Plans change infrequently; webhook on plan edit |
| `/dashboard`            | SSR                | Never cached            | `cache: "no-store"` | User-specific data; always fresh                |
| `/settings`             | SSR                | Never cached            | `cache: "no-store"` | User-specific data; always fresh                |
| `/login`                | Static             | N/A                     | Static at build     | No dynamic data; client component handles form  |

### Content Detail Caching Logic

The `WordPressClient.getContent()` method uses conditional caching:

```typescript
async getContent(slug: string, token?: string) {
  return this.request(`/content/${slug}`, {
    token,
    cache: token ? "no-store" : undefined,
    revalidate: token ? undefined : 300,
    tags: ["content"],
  });
}
```

- **Anonymous visitor**: ISR with 300s TTL. Sees metadata + excerpt only.
- **Authenticated user**: No cache (`no-store`). WordPress evaluates access on
  every request so tier changes take effect immediately.

---

## 6. Security Architecture

### 6.1 JWT Token Lifecycle

```
  Registration / Login
       |
       v
  WordPress generates token pair:
    - Access Token  (HS256, exp: 1 hour)
        payload: {iss, iat, nbf, exp, sub, type:"access",
                  data: {user_id, email, roles}}
    - Refresh Token (HS256, exp: 7 days)
        payload: {iss, iat, nbf, exp, sub, type:"refresh", jti: uuid}
       |
       v
  Next.js BFF stores in httpOnly cookies:
    - jenga_access_token:  httpOnly, secure, sameSite=lax, maxAge=3600
    - jenga_refresh_token: httpOnly, secure, sameSite=lax, maxAge=604800
       |
       v
  On each protected request:
    1. Edge Middleware checks cookie exists (fast gate, no validation)
    2. Server component reads token, sends as Bearer header to WordPress
    3. WordPress Auth\Middleware validates signature + expiry + issuer
    4. Sets current user via wp_set_current_user()
       |
       v
  On token expiry:
    1. WordPress returns 401
    2. Client calls POST /api/auth {action: "refresh"}
    3. BFF reads refresh_token cookie, calls /auth/refresh
    4. WordPress validates refresh token, issues new pair
    5. BFF sets new cookies
       |
       v
  On refresh token expiry:
    1. User is logged out (cookies cleared)
    2. Redirect to /login
```

**Security properties:**
- Tokens are **never exposed to client-side JavaScript** (httpOnly cookies).
- Tokens are **never stored in localStorage or sessionStorage**.
- Short access token lifetime (1h) limits the damage window.
- Refresh tokens carry a unique `jti` (JWT ID) for potential revocation.
- `secure` flag ensures cookies are only sent over HTTPS in production.
- `sameSite=lax` prevents CSRF on cross-origin POST requests.

### 6.2 CORS Configuration

Configured in `Plugin::add_cors_headers()`:

| Header                          | Value                                                   |
|---------------------------------|---------------------------------------------------------|
| `Access-Control-Allow-Origin`   | `JENGA_FRONTEND_URL` (single origin, not wildcard)      |
| `Access-Control-Allow-Methods`  | `GET, POST, PUT, PATCH, DELETE, OPTIONS`                |
| `Access-Control-Allow-Headers`  | `Authorization, Content-Type, X-WP-Nonce, X-Jenga-Nonce` |
| `Access-Control-Allow-Credentials` | `true`                                               |
| `Access-Control-Max-Age`        | `86400` (24 hours -- preflight cache)                   |

The default WordPress `rest_send_cors_headers` filter is removed and replaced
with the plugin's stricter configuration that only allows the configured
frontend origin.

### 6.3 Rate Limiting

Implemented via `API\Middleware\RateLimiter` using WordPress transients as the
storage backend (sliding window counter).

| Parameter          | Default | Config Constant              |
|--------------------|---------|------------------------------|
| Max requests       | 60      | `JENGA_RATE_LIMIT_REQUESTS`  |
| Window (seconds)   | 60      | `JENGA_RATE_LIMIT_WINDOW`    |

Behavior:
- Scoped to `/jenga/v1` namespace only (does not affect core WP REST).
- Webhook endpoints (`/webhooks/*`) are excluded (they have signature auth).
- Client IP detection chain: `CF-Connecting-IP` -> `X-Forwarded-For` ->
  `X-Real-IP` -> `REMOTE_ADDR`.
- Response headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
  `X-RateLimit-Reset`.
- Returns `429 Too Many Requests` when limit exceeded.

**Production note**: Replace transient-based storage with Redis or a dedicated
rate-limiting service for multi-server deployments.

### 6.4 CSP Headers

Content Security Policy headers should be configured at the Vercel/hosting
level or in `next.config.js`. Recommended policy:

```
default-src 'self';
script-src 'self' https://js.stripe.com;
frame-src https://js.stripe.com https://hooks.stripe.com;
connect-src 'self' https://api.stripe.com;
img-src 'self' data: https://*.gravatar.com https://*.wp.com;
style-src 'self' 'unsafe-inline';
```

Stripe.js requires script and frame access to Stripe domains for the
Checkout redirect and Elements integration.

### 6.5 httpOnly Cookie Storage

All authentication tokens are stored exclusively in httpOnly cookies:

| Cookie Name              | httpOnly | Secure       | SameSite | Max-Age  | Path |
|--------------------------|----------|--------------|----------|----------|------|
| `jenga_access_token`     | Yes      | Prod only    | Lax      | 3600s    | `/`  |
| `jenga_refresh_token`    | Yes      | Prod only    | Lax      | 604800s  | `/`  |

This eliminates XSS-based token theft because `document.cookie` cannot access
httpOnly cookies.

### 6.6 Stripe Webhook Security

- Stripe webhook events are verified using `Stripe\Webhook::constructEvent()`.
- The `stripe-signature` header is validated against `JENGA_STRIPE_WEBHOOK_SECRET`.
- The `permission_callback` is `__return_true` because authentication is handled
  by Stripe's signature verification, not JWT.

### 6.7 Revalidation Secret

The on-demand ISR revalidation endpoint (`/api/revalidate`) is protected by a
shared secret (`REVALIDATION_SECRET` / `JENGA_REVALIDATION_SECRET`) that must
match between WordPress and Next.js. This prevents unauthorized cache
invalidation.

---

## 7. Caching Strategy

### 7.1 Multi-Layer Cache Architecture

```
  Layer 1: WordPress Transient Cache
    |  (Rate limiter state, frequently queried data)
    |
    v
  Layer 2: Next.js Data Cache (fetch-level)
    |  (WordPress API responses, per-fetch revalidation)
    |
    v
  Layer 3: Next.js Full Route Cache (ISR)
    |  (Pre-rendered HTML pages with TTLs)
    |
    v
  Layer 4: Vercel CDN Edge Cache
    |  (Serves ISR pages from edge locations)
    |
    v
  Browser
```

### 7.2 WordPress Transient Cache

Used for:
- **Rate limiter counters**: Per-IP request counts stored as transients with
  a TTL equal to the rate limit window (default 60s).
- Key format: `jenga_rl_{md5(ip)}`

For production, transients should be backed by an object cache (Redis/Memcached)
via a WordPress object cache drop-in for multi-server consistency.

### 7.3 Next.js ISR Cache

The `WordPressClient` applies Next.js cache directives at the fetch level:

| API Call              | `revalidate`  | `tags`       | `cache`    |
|-----------------------|---------------|--------------|------------|
| `getContentList()`    | 60            | `["content"]`| (default)  |
| `getContent()` (anon) | 300           | `["content"]`| (default)  |
| `getContent()` (auth) | --            | `["content"]`| `no-store` |
| `getPlans()`          | 3600          | `["plans"]`  | (default)  |
| `getPlan()`           | 3600          | `["plans"]`  | (default)  |
| `getMe()`             | --            | --           | `no-store` |
| `getCurrentSubscription()` | --       | --           | `no-store` |

User-specific data (`getMe`, `getCurrentSubscription`) always bypasses cache.

### 7.4 On-Demand Revalidation via Webhooks

Two mechanisms trigger on-demand revalidation:

#### WordPress Content Changes (RevalidationDispatcher)

| Post Type         | Paths Revalidated                    | Trigger                |
|-------------------|--------------------------------------|------------------------|
| `jenga_content`   | `/content`, `/content/{slug}`        | `save_post`, `delete_post` |
| `jenga_plan`      | `/pricing`                           | `save_post`, `delete_post` |

The dispatcher fires as a non-blocking (`blocking: false`) HTTP POST to
`/api/revalidate` with a shared secret and array of paths.

#### Stripe Payment Events (StripeHandler)

| Event                              | Paths Revalidated             |
|------------------------------------|-------------------------------|
| `checkout.session.completed`       | `/dashboard`, `/content`      |
| `customer.subscription.deleted`    | `/dashboard`                  |

These are triggered synchronously within the webhook handler after subscription
state is updated.

### 7.5 CDN Edge Caching

Vercel's Edge Network caches ISR-generated pages automatically:
- First request triggers SSR; result is cached at the edge.
- Subsequent requests within the revalidation window serve the cached version.
- After TTL expiry, the next request triggers background regeneration
  (stale-while-revalidate).
- On-demand revalidation (`revalidatePath()`) purges the edge cache immediately.

### 7.6 Cache Invalidation Summary

```
Content saved in WordPress
    |
    +-- RevalidationDispatcher fires
    |       |
    |       +-- POST /api/revalidate {paths, secret}
    |               |
    |               +-- revalidatePath("/content")
    |               +-- revalidatePath("/content/{slug}")
    |               |
    |               +-- Next.js purges ISR cache for those paths
    |               +-- Vercel CDN purges edge cache
    |
    +-- Next visitor gets fresh page
```

```
Stripe event received
    |
    +-- StripeHandler processes event
    |       |
    |       +-- Updates WordPress subscription state
    |       +-- Assigns/removes user roles
    |       |
    |       +-- trigger_revalidation(["/dashboard", "/content"])
    |               |
    |               +-- POST /api/revalidate
    |               +-- Edge cache purged for those paths
    |
    +-- User sees updated subscription state on next page load
```

---

## Configuration Reference

All configuration is environment-based, read from constants or environment
variables via the `jenga_config()` helper in `config/settings.php`.

| Constant                      | Default              | Description                          |
|-------------------------------|----------------------|--------------------------------------|
| `JENGA_JWT_SECRET`            | `change-me-in-production` | HMAC secret for JWT signing     |
| `JENGA_JWT_EXPIRATION`        | `3600`               | Access token lifetime (seconds)      |
| `JENGA_JWT_REFRESH_EXPIRATION`| `604800`             | Refresh token lifetime (seconds)     |
| `JENGA_STRIPE_SECRET_KEY`     | (empty)              | Stripe secret key                    |
| `JENGA_STRIPE_PUBLISHABLE_KEY`| (empty)              | Stripe publishable key               |
| `JENGA_STRIPE_WEBHOOK_SECRET` | (empty)              | Stripe webhook signing secret        |
| `JENGA_FRONTEND_URL`          | `http://localhost:3000` | Frontend origin (CORS, redirects) |
| `JENGA_REVALIDATION_SECRET`   | `change-me`          | Shared secret for ISR revalidation   |
| `JENGA_RATE_LIMIT_REQUESTS`   | `60`                 | Max requests per window              |
| `JENGA_RATE_LIMIT_WINDOW`     | `60`                 | Rate limit window (seconds)          |

Frontend environment variables (`frontend/.env`):

| Variable                              | Default                          |
|---------------------------------------|----------------------------------|
| `NEXT_PUBLIC_WP_API_URL`              | `http://localhost:8080/wp-json`  |
| `NEXT_PUBLIC_APP_URL`                 | `http://localhost:3000`          |
| `NEXT_PUBLIC_APP_NAME`                | `Jenga`                          |
| `NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY`  | (empty)                          |
| `REVALIDATION_SECRET`                 | `change-me`                      |
