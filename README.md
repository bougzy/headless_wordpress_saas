# Jenga

**Headless WordPress SaaS Platform**

A content subscription platform -- think Substack or Patreon -- powered by WordPress as the backend engine and a Next.js 14 App Router frontend. WordPress handles authentication, content management, roles, and business logic through a custom OOP plugin, while Next.js delivers a fast, modern subscriber experience on the edge.

---

## Why WordPress as a Backend?

Using WordPress as a headless API server is a deliberate architectural choice, not a compromise.

- **Battle-tested user management.** WordPress ships with a mature users, roles, and capabilities system out of the box -- no need to build auth tables or permission logic from scratch.
- **Plugin ecosystem.** Over 20 years of plugins means you can extend business logic (email, analytics, SEO metadata) without reinventing the wheel.
- **Built-in admin UI.** Content editors and admins get the WordPress dashboard for free. No custom admin panel to build or maintain.
- **Security hardening.** Two decades of security patches, nonce verification, input sanitization, and the broader WordPress security community behind every release.
- **Custom post types as data models.** Plans, Subscriptions, and Content are registered as custom post types -- flexible, queryable, and backed by a proven storage layer.
- **REST API.** The WP REST API enables a clean decoupled architecture. The frontend never touches WordPress themes or PHP rendering.
- **Cost-effective.** Auth, CMS, roles, file uploads, revisions, and an admin interface -- all included. That is months of development you skip on day one.

---

## Architecture Overview

```
+------------------+         REST API (JWT)         +---------------------+
|                  | <----------------------------> |                     |
|   Next.js 14     |         /jenga/v1/*            |   WordPress 6.4+    |
|   (Vercel Edge)  |                                |  (Managed Hosting)  |
|                  | <---- Revalidation webhook --- |                     |
+------------------+                                +---------------------+
        |                                                    ^
        |  Stripe.js (client)                                |  Stripe Webhooks
        v                                                    |
+------------------+                                +---------------------+
|                  | ------- Checkout Session -----> |                     |
|   User Browser   |                                |       Stripe        |
|                  | <---- Payment confirmation ---- |                     |
+------------------+                                +---------------------+

Flow summary:
  1. Browser  -->  Next.js  -->  JWT Auth  -->  WordPress REST API
  2. Stripe Webhook  -->  WordPress  -->  Revalidation  -->  Next.js ISR
  3. Browser  -->  Stripe.js  -->  Checkout  -->  Stripe  -->  WordPress
```

---

## Tech Stack

### Backend

| Component | Technology |
|-----------|-----------|
| CMS / API Server | WordPress 6.4+ |
| Language | PHP 8.1+ (strict types) |
| Plugin Architecture | OOP with PSR-4 autoloading (Composer) |
| Authentication | JWT via `firebase/php-jwt` ^6.10 |
| Payments | `stripe/stripe-php` ^13.0 |
| API Namespace | `jenga/v1` |

### Frontend

| Component | Technology |
|-----------|-----------|
| Framework | Next.js 14 (App Router) |
| Language | TypeScript 5.4 |
| Styling | Tailwind CSS 3.4 |
| Payments (client) | Stripe.js `@stripe/stripe-js` ^3.0 |
| Date handling | date-fns 3.6 |

### Infrastructure

| Layer | Service |
|-------|---------|
| Frontend hosting | Vercel (Edge + Serverless) |
| Backend hosting | Managed WordPress (e.g., Cloudways, SpinupWP, GridPane) |
| Payments | Stripe |

---

## Project Structure

```
headless-wordpress-saas/
|
|-- frontend/                        # Next.js application
|   |-- app/
|   |   |-- page.tsx                 # Landing page
|   |   |-- layout.tsx               # Root layout
|   |   |-- globals.css
|   |   |-- (public)/                # Public route group
|   |   |   |-- pricing/page.tsx
|   |   |   |-- content/page.tsx
|   |   |   |-- content/[slug]/page.tsx
|   |   |   |-- login/page.tsx
|   |   |-- (dashboard)/             # Authenticated route group
|   |   |   |-- layout.tsx           # Dashboard layout with auth gate
|   |   |   |-- dashboard/page.tsx
|   |   |   |-- settings/page.tsx
|   |   |-- api/
|   |       |-- auth/route.ts        # Auth proxy (set httpOnly cookies)
|   |       |-- revalidate/route.ts  # On-demand ISR revalidation
|   |       |-- stripe/webhook/route.ts
|   |-- components/
|   |   |-- ui/                      # Primitives: button, card, input, badge
|   |   |-- layout/                  # Header, Footer
|   |   |-- features/                # Content card, pricing card, login form
|   |-- lib/
|   |   |-- auth.ts                  # JWT helpers + cookie management
|   |   |-- wordpress.ts             # WP REST API client
|   |   |-- stripe.ts                # Stripe client-side utilities
|   |   |-- constants.ts             # Tier labels, ISR intervals, env vars
|   |-- middleware.ts                 # Edge middleware: route protection
|   |-- types/index.ts               # Shared TypeScript interfaces
|   |-- .env.example
|   |-- package.json
|   |-- tsconfig.json
|   |-- tailwind.config.js
|   |-- next.config.js
|
|-- wordpress/
    |-- plugins/
        |-- jenga-saas-core/
            |-- jenga-saas-core.php  # Plugin bootstrap + activation hooks
            |-- composer.json
            |-- config/
            |   |-- settings.php     # Environment-based configuration
            |-- src/
                |-- Plugin.php       # Singleton orchestrator
                |-- Auth/
                |   |-- JWT.php              # Token encode/decode/refresh
                |   |-- Middleware.php        # Permission callback for routes
                |-- PostTypes/
                |   |-- Plan.php             # Subscription plan CPT
                |   |-- Subscription.php     # User subscription CPT
                |   |-- Content.php          # Gated content CPT
                |-- Roles/
                |   |-- RoleManager.php      # Custom roles + capabilities
                |-- Payments/
                |   |-- StripeHandler.php    # Checkout, portal, subscription logic
                |-- API/
                |   |-- Middleware/
                |   |   |-- RateLimiter.php  # Per-IP rate limiting
                |   |-- V1/
                |       |-- AuthController.php
                |       |-- PlanController.php
                |       |-- ContentController.php
                |       |-- SubscriptionController.php
                |       |-- WebhookController.php
                |-- Webhooks/
                    |-- RevalidationDispatcher.php  # Notify Next.js on content change
```

---

## Features

### Authentication

- JWT access tokens (1-hour expiry) and refresh tokens (7-day expiry) via `firebase/php-jwt`
- Tokens stored in `httpOnly` cookies -- never exposed to client-side JavaScript
- Next.js Edge Middleware protects `/dashboard` and `/settings` routes
- Auth proxy route (`/api/auth`) sets secure cookies server-side
- Automatic redirect-after-login with query parameter preservation

### Content Gating (3-Tier System)

| Tier | Value | Access |
|------|-------|--------|
| Free | 0 | All visitors |
| Pro | 1 | Pro and Premium subscribers |
| Premium | 2 | Premium subscribers only |

Content access is enforced at the API level (WordPress) and the rendering level (Next.js). Unauthenticated or under-tiered users receive content previews only.

### Payments (Stripe)

- Stripe Checkout for subscription creation
- Stripe Customer Portal for self-service subscription management
- Stripe Webhooks processed by WordPress (`checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`)
- Subscription status synced to WordPress custom post types
- Frontend webhook route (`/api/stripe/webhook`) available for additional client-side handling

### Performance

- Incremental Static Regeneration (ISR) for content listings and individual posts
- Server-Side Rendering (SSR) for authenticated dashboard pages
- React Server Components for zero client-side JS on static pages
- CDN-friendly public pages with configurable revalidation intervals
- On-demand revalidation triggered by WordPress content changes

### Security

- Per-IP rate limiting on all `jenga/v1` API routes (configurable window and threshold)
- CORS restricted to the configured frontend origin
- `httpOnly` + `Secure` + `SameSite` cookie attributes for tokens
- WordPress nonce validation on state-changing operations
- Stripe webhook signature verification
- CSP headers and OWASP-aligned security practices

---

## Getting Started

### Prerequisites

- **PHP** 8.1+
- **Composer** 2.x
- **WordPress** 6.4+
- **Node.js** 18+ and **npm** 9+
- **Stripe account** (test mode is fine for development)

### 1. WordPress Setup

```bash
# Navigate to the plugin directory
cd wordpress/plugins/jenga-saas-core

# Install PHP dependencies
composer install

# Add the following constants to your wp-config.php:
# define('JENGA_JWT_SECRET', 'your-secure-secret');
# define('JENGA_STRIPE_SECRET_KEY', 'sk_test_xxx');
# define('JENGA_STRIPE_PUBLISHABLE_KEY', 'pk_test_xxx');
# define('JENGA_STRIPE_WEBHOOK_SECRET', 'whsec_xxx');
# define('JENGA_FRONTEND_URL', 'http://localhost:3000');
# define('JENGA_REVALIDATION_SECRET', 'your-revalidation-secret');
```

Activate the **Jenga SaaS Core** plugin from the WordPress admin. On activation, the plugin registers custom roles and post types automatically.

### 2. Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Copy environment file and fill in values
cp .env.example .env.local

# Start development server
npm run dev
```

### Environment Variables (Frontend)

| Variable | Description |
|----------|-------------|
| `NEXT_PUBLIC_WP_API_URL` | WordPress REST API base URL (e.g., `https://api.example.com/wp-json`) |
| `NEXT_PUBLIC_WP_HOSTNAME` | WordPress hostname for image domain allowlisting |
| `JWT_SECRET` | Must match `JENGA_JWT_SECRET` in WordPress |
| `NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `STRIPE_SECRET_KEY` | Stripe secret key (server-side only) |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret |
| `REVALIDATION_SECRET` | Must match `JENGA_REVALIDATION_SECRET` in WordPress |
| `NEXT_PUBLIC_APP_URL` | Public URL of the frontend |
| `NEXT_PUBLIC_APP_NAME` | Application display name |

### Docker Compose Quick Start

```yaml
# docker-compose.yml (development)
version: "3.9"

services:
  wordpress:
    image: wordpress:6.4-php8.1-apache
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: jenga
      WORDPRESS_DB_PASSWORD: jenga_dev
      WORDPRESS_DB_NAME: jenga
    volumes:
      - wp_data:/var/www/html
      - ./wordpress/plugins/jenga-saas-core:/var/www/html/wp-content/plugins/jenga-saas-core

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: jenga
      MYSQL_USER: jenga
      MYSQL_PASSWORD: jenga_dev
    volumes:
      - db_data:/var/lib/mysql

volumes:
  wp_data:
  db_data:
```

```bash
docker compose up -d
```

Then run `npm run dev` in the `frontend/` directory to start the Next.js dev server.

---

## API Reference

All custom endpoints are registered under the `jenga/v1` namespace.

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/jenga/v1/auth/login` | POST | No | Authenticate and receive JWT tokens |
| `/jenga/v1/auth/refresh` | POST | No | Refresh an expired access token |
| `/jenga/v1/auth/me` | GET | Yes | Get current user profile |
| `/jenga/v1/plans` | GET | No | List available subscription plans |
| `/jenga/v1/content` | GET | No | List content (respects tier gating) |
| `/jenga/v1/content/{slug}` | GET | Optional | Get single content (full body requires tier) |
| `/jenga/v1/subscriptions` | GET | Yes | Get current user subscriptions |
| `/jenga/v1/subscriptions/checkout` | POST | Yes | Create Stripe Checkout session |
| `/jenga/v1/subscriptions/portal` | POST | Yes | Create Stripe Customer Portal session |
| `/jenga/v1/webhooks/stripe` | POST | No | Stripe webhook receiver (signature verified) |

For full request/response schemas, see `docs/api.md`.

---

## Deployment

### Frontend (Vercel)

1. Connect your repository to Vercel.
2. Set the root directory to `frontend/`.
3. Add all environment variables from `.env.example` to the Vercel project settings.
4. Deploy. Vercel automatically detects Next.js and configures the build.

### Backend (Managed WordPress)

1. Use a managed WordPress host (Cloudways, SpinupWP, GridPane, or similar).
2. Upload the `jenga-saas-core` plugin to `wp-content/plugins/`.
3. Run `composer install --no-dev --optimize-autoloader` in the plugin directory.
4. Add all `JENGA_*` constants to `wp-config.php`.
5. Activate the plugin and verify the REST API is accessible at `/wp-json/jenga/v1/plans`.

### Stripe Webhook Configuration

Register the following webhook endpoint in your Stripe Dashboard:

- **URL:** `https://your-wordpress-domain.com/wp-json/jenga/v1/webhooks/stripe`
- **Events:** `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`

---

## Rendering Strategy

| Page | Strategy | Revalidation | Auth Required |
|------|----------|-------------|---------------|
| `/` (Landing) | SSG | Build time | No |
| `/pricing` | ISR | 3600s (1 hour) | No |
| `/content` | ISR | 60s (1 minute) | No |
| `/content/[slug]` | ISR | 300s (5 minutes) | Optional (tier gating) |
| `/login` | SSR | None | No (redirects if authed) |
| `/dashboard` | SSR | None | Yes |
| `/settings` | SSR | None | Yes |

---

## Lighthouse Targets

| Metric | Target |
|--------|--------|
| Performance | 90+ |
| Accessibility | 95+ |
| SEO | 95+ |
| Best Practices | 95+ |

---

## License

MIT
