# Architecture Decisions and Trade-offs

This document explains the key technical decisions made in the Jenga platform and the reasoning behind each.

---

## 1. WordPress as Backend (Not Just a CMS)

**Decision:** Use WordPress as a full backend engine with custom business logic, not just as a content repository.

**Reasoning:**
- WordPress provides battle-tested user management, roles, and capabilities out of the box
- The admin dashboard serves as a free back-office interface for managing plans, subscriptions, and content
- Custom post types replace traditional database tables while providing a familiar editing interface
- The plugin ecosystem allows extending functionality (email, analytics, backups) without custom code
- 20+ years of security patches and community-driven hardening
- REST API is mature and well-documented

**Trade-offs:**
- PHP performance is generally lower than Node.js/Go/Rust for API workloads
- WordPress database schema (wp_posts, wp_postmeta) is not optimized for SaaS data patterns
- Plugin conflicts can introduce instability
- Scaling horizontally requires more effort than with stateless API frameworks

**Mitigations:**
- Object caching (Redis) for database query performance
- ISR on the frontend reduces API load significantly
- Custom plugin avoids third-party plugin conflicts
- Managed hosting handles scaling concerns for most use cases

---

## 2. JWT Over Session-Based Auth

**Decision:** Use JWT tokens instead of WordPress session cookies.

**Reasoning:**
- Stateless authentication works across different domains (WordPress on domain A, Next.js on domain B)
- No server-side session storage needed
- Works with serverless deployments (Vercel Edge/Lambda)
- Standard token format understood by any client

**Trade-offs:**
- Cannot be revoked server-side (unlike sessions)
- Token size is larger than a session cookie
- Requires careful secret management

**Mitigations:**
- Short access token lifetime (1 hour) limits the revocation window
- Refresh tokens provide a natural rotation mechanism
- httpOnly cookies prevent client-side token theft

---

## 3. httpOnly Cookies Over localStorage

**Decision:** Store JWT tokens in httpOnly cookies, not localStorage or sessionStorage.

**Reasoning:**
- httpOnly cookies are not accessible to JavaScript, preventing XSS-based token theft
- Cookies are sent automatically with requests, simplifying the auth flow
- SameSite attribute provides CSRF protection
- Server components can read cookies directly without client-side hydration

**Trade-offs:**
- Cookies have size limits (4KB), though JWTs are well within this
- Cross-domain setups require careful CORS and cookie configuration
- Cannot be read by client-side JavaScript (which is the point)

---

## 4. Next.js API Routes as BFF (Backend-for-Frontend)

**Decision:** Use Next.js API routes to proxy authentication and payment requests instead of calling WordPress directly from the browser.

**Reasoning:**
- Sensitive operations (login, checkout) stay server-side
- WordPress URL is not exposed to the client
- Cookies can only be set from the server
- Consolidates error handling and response normalization

**Trade-offs:**
- Adds an extra network hop (browser → Next.js → WordPress)
- More code to maintain (API routes + WordPress endpoints)

**Mitigations:**
- Extra latency is negligible for auth/payment operations
- BFF pattern is industry-standard for security-sensitive operations

---

## 5. ISR Over Full SSR

**Decision:** Use Incremental Static Regeneration (ISR) for public content pages instead of server-side rendering on every request.

**Reasoning:**
- Static pages served from CDN edge are faster than SSR
- Reduces load on WordPress backend
- On-demand revalidation via webhooks keeps content fresh
- Vercel's ISR implementation is production-proven

**Rendering strategy by page:**

| Page | Strategy | Reason |
|------|----------|--------|
| Home | ISR (60s) | Content changes infrequently |
| Content list | ISR (60s) + on-demand | New articles trigger revalidation |
| Content detail | ISR (300s) or no-store | Public = ISR, authenticated = fresh |
| Pricing | ISR (3600s) | Plans rarely change |
| Dashboard | SSR (no-store) | Always needs fresh user data |
| Login | Static | No dynamic content |

**Trade-offs:**
- Content updates have a delay (up to revalidation interval)
- On-demand revalidation adds complexity

**Mitigations:**
- Webhook-triggered revalidation on content save in WordPress
- Authenticated requests bypass ISR cache entirely

---

## 6. Custom Post Types Over Custom Database Tables

**Decision:** Use WordPress custom post types (CPTs) for Plans, Subscriptions, and Content instead of creating custom MySQL tables.

**Reasoning:**
- CPTs integrate with the WordPress admin UI automatically
- Built-in revision history, status management, and search
- Meta fields provide flexible schema without migrations
- WP_Query provides a powerful query builder

**Trade-offs:**
- The EAV (Entity-Attribute-Value) pattern of wp_postmeta is slower than dedicated columns
- Complex queries require meta_query which generates suboptimal SQL
- No foreign key constraints at the database level

**Mitigations:**
- Add MySQL indexes on frequently queried meta keys
- Object caching reduces repeated query overhead
- For high-scale deployments, consider migrating to custom tables with a migration tool

---

## 7. Stripe Over Custom Payment System

**Decision:** Use Stripe Checkout and Billing Portal for payment handling.

**Reasoning:**
- PCI compliance handled by Stripe (no card data touches our servers)
- Checkout and Billing Portal are pre-built, tested UIs
- Webhook system ensures reliable event processing
- Industry-standard API with excellent documentation

**Trade-offs:**
- Stripe fees (2.9% + 30c per transaction)
- Less control over the checkout UI
- Dependent on Stripe's availability

---

## 8. Tailwind CSS Over Component Libraries

**Decision:** Use Tailwind CSS with custom components instead of a pre-built component library like Shadcn/ui or MUI.

**Reasoning:**
- Full control over design without fighting library opinions
- Smaller bundle size (only used utilities are included)
- Demonstrates UI engineering capability
- No dependency on third-party component updates

**Trade-offs:**
- More components to build from scratch
- Verbose class names in templates
- Consistency requires discipline

---

## 9. Rate Limiting with Transients

**Decision:** Implement rate limiting using WordPress transients (database-backed) instead of an external service.

**Reasoning:**
- Zero additional infrastructure required
- Simple to implement and understand
- Sufficient for moderate traffic levels

**Trade-offs:**
- Database-backed transients are slower than Redis
- Not distributed across multiple WordPress instances
- Approximate rather than precise rate limiting

**Mitigations:**
- Replace transients with Redis in production (`wp_cache` with Redis object cache)
- Add Cloudflare rate limiting as an additional layer
- For high-scale: use a dedicated rate limiting service

---

## 10. Monorepo Structure

**Decision:** Keep WordPress plugin and Next.js frontend in the same repository.

**Reasoning:**
- Easier to maintain and version together
- Shared documentation and configuration
- Docker Compose can orchestrate both services
- PRs can include both frontend and backend changes

**Trade-offs:**
- Larger repository size
- Different deployment pipelines for each service
- Teams may only need one part

**Mitigations:**
- Clear directory separation (wordpress/ and frontend/)
- Independent package.json and composer.json
- Can be split into separate repos if team structure demands it
