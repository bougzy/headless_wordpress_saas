# Deployment Guide

This guide covers deploying the Jenga platform to production with Vercel (frontend) and managed WordPress hosting (backend).

---

## Architecture Overview

```
                    ┌─────────────────┐
                    │   Cloudflare /   │
                    │   Vercel Edge    │
                    │   (CDN + Edge)   │
                    └────────┬────────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
    ┌─────────▼─────────┐       ┌───────────▼──────────┐
    │   Vercel           │       │  Managed WordPress   │
    │   (Next.js)        │◄─────►│  (REST API)          │
    │   Frontend         │       │  Backend             │
    └─────────┬─────────┘       └───────────┬──────────┘
              │                             │
              │                   ┌─────────▼─────────┐
              │                   │   MySQL Database   │
              │                   └───────────────────┘
              │
    ┌─────────▼─────────┐
    │   Stripe           │
    │   (Payments)       │
    └───────────────────┘
```

---

## 1. WordPress Backend Deployment

### Recommended Hosting Providers

| Provider | Why |
|----------|-----|
| **Cloudways** | Managed VPS with PHP 8.1+, good for custom plugins |
| **SpinupWP** | Server management panel for DigitalOcean/Vultr |
| **GridPane** | Performance-focused WordPress hosting |
| **AWS Lightsail** | Cost-effective, full control |

Avoid shared hosting — you need PHP 8.1+, Composer access, and SSH.

### Steps

1. **Set up WordPress** on your hosting provider with PHP 8.1+ and MySQL 8.0+.

2. **Upload the plugin:**
   ```bash
   cd wordpress/plugins/jenga-saas-core
   composer install --no-dev --optimize-autoloader
   ```
   Upload the `jenga-saas-core` folder to `wp-content/plugins/`.

3. **Activate the plugin** in WP Admin > Plugins.

4. **Configure environment variables** in `wp-config.php`:
   ```php
   // JWT Authentication
   define('JENGA_JWT_SECRET', 'your-secure-random-string-min-32-chars');
   define('JENGA_JWT_EXPIRATION', 3600);
   define('JENGA_JWT_REFRESH_EXPIRATION', 604800);

   // Stripe
   define('JENGA_STRIPE_SECRET_KEY', 'sk_live_xxx');
   define('JENGA_STRIPE_PUBLISHABLE_KEY', 'pk_live_xxx');
   define('JENGA_STRIPE_WEBHOOK_SECRET', 'whsec_xxx');

   // Frontend URL (for CORS and redirects)
   define('JENGA_FRONTEND_URL', 'https://your-app.vercel.app');

   // Revalidation
   define('JENGA_REVALIDATION_SECRET', 'your-revalidation-secret');
   ```

5. **Set up Stripe products and prices** in the Stripe Dashboard, then create Plan posts in WP Admin with the corresponding Stripe Price IDs.

6. **Configure Stripe webhook** in the Stripe Dashboard:
   - Endpoint URL: `https://your-wordpress.com/wp-json/jenga/v1/webhooks/stripe`
   - Events: `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_failed`

7. **Security hardening:**
   - Disable XML-RPC: `add_filter('xmlrpc_enabled', '__return_false');`
   - Disable REST API user enumeration for non-admin users
   - Use a security plugin (Wordfence, Sucuri) for additional protection
   - Force HTTPS

---

## 2. Next.js Frontend Deployment (Vercel)

### Steps

1. **Push to GitHub** (or GitLab/Bitbucket).

2. **Import in Vercel:**
   - Go to [vercel.com/new](https://vercel.com/new)
   - Import the repository
   - Set the root directory to `frontend`
   - Framework: Next.js (auto-detected)

3. **Configure environment variables** in Vercel dashboard:

   | Variable | Value | Notes |
   |----------|-------|-------|
   | `NEXT_PUBLIC_WP_API_URL` | `https://your-wordpress.com/wp-json` | WordPress REST API base |
   | `NEXT_PUBLIC_WP_HOSTNAME` | `your-wordpress.com` | For Next.js Image optimization |
   | `NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY` | `pk_live_xxx` | Stripe publishable key |
   | `STRIPE_SECRET_KEY` | `sk_live_xxx` | Server-side only |
   | `STRIPE_WEBHOOK_SECRET` | `whsec_xxx` | Server-side only |
   | `REVALIDATION_SECRET` | `your-secret` | Must match WordPress config |
   | `NEXT_PUBLIC_APP_URL` | `https://your-app.vercel.app` | Canonical URL |
   | `NEXT_PUBLIC_APP_NAME` | `Jenga` | Displayed in UI |

4. **Deploy.** Vercel will build and deploy automatically on push.

---

## 3. Stripe Configuration

### Products and Prices

Create products in the Stripe Dashboard:

| Product | Price | Recurring |
|---------|-------|-----------|
| Free | $0 | - |
| Pro | $9.90/mo | Monthly |
| Premium | $19.90/mo | Monthly |

Copy each Price ID (`price_xxx`) and set it on the corresponding Plan post in WordPress (`_jenga_plan_stripe_price` meta field).

### Webhook Configuration

1. Go to Stripe Dashboard > Developers > Webhooks
2. Add endpoint: `https://your-wordpress.com/wp-json/jenga/v1/webhooks/stripe`
3. Select events:
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_failed`
   - `invoice.payment_succeeded`
4. Copy the signing secret to `JENGA_STRIPE_WEBHOOK_SECRET`

### Testing

Use Stripe CLI for local testing:
```bash
stripe listen --forward-to localhost:8080/wp-json/jenga/v1/webhooks/stripe
```

---

## 4. Domain and SSL

- **Frontend:** Configure custom domain in Vercel dashboard. SSL is automatic.
- **WordPress:** Configure SSL via your hosting provider or Cloudflare.
- **CORS:** Ensure `JENGA_FRONTEND_URL` in WordPress matches your production frontend domain exactly.

---

## 5. Post-Deployment Checklist

- [ ] WordPress plugin activated and roles created
- [ ] JWT secret is a strong random string (32+ characters)
- [ ] Stripe webhook endpoint configured and verified
- [ ] All environment variables set in both WordPress and Vercel
- [ ] CORS allows only your frontend domain
- [ ] SSL/HTTPS enforced on both services
- [ ] Plans created in WordPress with Stripe Price IDs
- [ ] Test the full flow: register → subscribe → access gated content
- [ ] Rate limiting is working (check X-RateLimit headers)
- [ ] ISR revalidation is working (edit content in WP, verify frontend updates)
- [ ] Security headers present (check with securityheaders.com)
- [ ] Lighthouse audit passes targets (90+ Performance, 95+ Accessibility)

---

## 6. Monitoring

### Recommended Tools

- **Vercel Analytics**: Built-in performance monitoring
- **Stripe Dashboard**: Payment monitoring and alerts
- **WordPress**: Query Monitor plugin for API performance
- **Uptime**: UptimeRobot or Better Uptime for both services
- **Error tracking**: Sentry for both Next.js and WordPress
