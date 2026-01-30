// ─── User & Auth ───────────────────────────────────────────────

export interface User {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  display_name: string;
  avatar: string;
  roles: string[];
  tier: number;
  subscription: Subscription | null;
  created_at: string;
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  token_type: string;
}

export interface AuthResponse {
  user: User;
  tokens: AuthTokens;
}

// ─── Plans ─────────────────────────────────────────────────────

export interface Plan {
  id: number;
  name: string;
  description: string;
  slug: string;
  price: number;
  stripe_price: string;
  features: string[];
  tier: number;
  active: boolean;
}

// ─── Subscriptions ─────────────────────────────────────────────

export interface Subscription {
  id: number;
  user_id: number;
  plan_id: number;
  plan: Plan | null;
  stripe_id: string;
  status: "active" | "cancelled" | "past_due" | "trialing" | "expired";
  current_period_end: number;
  created_at: number;
}

// ─── Content ───────────────────────────────────────────────────

export interface ContentAuthor {
  id: number;
  name: string;
  avatar: string;
}

export interface ContentTopic {
  id: number;
  name: string;
  slug: string;
}

export interface ContentItem {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  tier: number;
  tier_label: string;
  read_time: number;
  topics: ContentTopic[];
  featured_image: string | null;
  author: ContentAuthor | null;
  published_at: string;
  updated_at: string;
  body?: string;
  has_access?: boolean;
  upgrade_message?: string;
}

export interface PaginationMeta {
  total: number;
  pages: number;
  current_page: number;
  per_page: number;
}

// ─── API Responses ─────────────────────────────────────────────

export interface ApiResponse<T> {
  data: T;
}

export interface ApiListResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface ApiError {
  code: string;
  message: string;
  data?: { status: number };
}

// ─── Checkout ──────────────────────────────────────────────────

export interface CheckoutResponse {
  checkout_url: string;
  session_id: string;
}

export interface PortalResponse {
  portal_url: string;
}
