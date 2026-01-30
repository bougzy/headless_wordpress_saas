export const WP_API_URL =
  process.env.NEXT_PUBLIC_WP_API_URL || "http://localhost:8080/wp-json";

export const APP_URL =
  process.env.NEXT_PUBLIC_APP_URL || "http://localhost:3000";

export const APP_NAME = process.env.NEXT_PUBLIC_APP_NAME || "Jenga";

export const STRIPE_PUBLISHABLE_KEY =
  process.env.NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY || "";

export const REVALIDATION_SECRET =
  process.env.REVALIDATION_SECRET || "change-me";

export const TIER_LABELS: Record<number, string> = {
  0: "Free",
  1: "Pro",
  2: "Premium",
};

export const TIER_COLORS: Record<number, string> = {
  0: "bg-gray-100 text-gray-700",
  1: "bg-brand-100 text-brand-700",
  2: "bg-amber-100 text-amber-700",
};

// ISR revalidation intervals (in seconds)
export const REVALIDATE_CONTENT_LIST = 60; // 1 minute
export const REVALIDATE_CONTENT_SINGLE = 300; // 5 minutes
export const REVALIDATE_PLANS = 3600; // 1 hour
