import { WP_API_URL } from "./constants";
import type {
  ApiResponse,
  ApiListResponse,
  AuthResponse,
  Plan,
  ContentItem,
  Subscription,
  CheckoutResponse,
  PortalResponse,
  User,
} from "@/types";

/**
 * WordPress REST API client for the Jenga SaaS platform.
 *
 * Handles all communication with the WordPress backend,
 * including authenticated requests with JWT tokens.
 */

type FetchOptions = {
  method?: string;
  body?: unknown;
  token?: string;
  tags?: string[];
  revalidate?: number;
  cache?: RequestCache;
};

class WordPressClient {
  private baseUrl: string;

  constructor(baseUrl: string) {
    this.baseUrl = baseUrl;
  }

  /**
   * Base fetch wrapper with error handling and JWT auth support.
   */
  private async request<T>(
    endpoint: string,
    options: FetchOptions = {}
  ): Promise<T> {
    const { method = "GET", body, token, tags, revalidate, cache } = options;

    const headers: Record<string, string> = {
      "Content-Type": "application/json",
    };

    if (token) {
      headers["Authorization"] = `Bearer ${token}`;
    }

    const fetchOptions: RequestInit & { next?: Record<string, unknown> } = {
      method,
      headers,
    };

    if (body) {
      fetchOptions.body = JSON.stringify(body);
    }

    // Next.js cache control
    if (tags || revalidate !== undefined) {
      fetchOptions.next = {};
      if (tags) fetchOptions.next.tags = tags;
      if (revalidate !== undefined) fetchOptions.next.revalidate = revalidate;
    }

    if (cache) {
      fetchOptions.cache = cache;
    }

    const url = `${this.baseUrl}/jenga/v1${endpoint}`;
    const response = await fetch(url, fetchOptions);

    if (!response.ok) {
      const error = await response.json().catch(() => ({
        message: "An unexpected error occurred",
        code: "unknown_error",
      }));
      throw new ApiError(
        error.message || "Request failed",
        response.status,
        error.code
      );
    }

    return response.json();
  }

  // ─── Auth ──────────────────────────────────────────────────

  async login(email: string, password: string): Promise<AuthResponse> {
    return this.request<AuthResponse>("/auth/login", {
      method: "POST",
      body: { email, password },
    });
  }

  async register(data: {
    email: string;
    password: string;
    first_name?: string;
    last_name?: string;
  }): Promise<AuthResponse> {
    return this.request<AuthResponse>("/auth/register", {
      method: "POST",
      body: data,
    });
  }

  async refreshTokens(
    refreshToken: string
  ): Promise<{ tokens: AuthResponse["tokens"] }> {
    return this.request("/auth/refresh", {
      method: "POST",
      body: { refresh_token: refreshToken },
    });
  }

  async getMe(token: string): Promise<{ user: User }> {
    return this.request("/auth/me", { token, cache: "no-store" });
  }

  // ─── Plans ─────────────────────────────────────────────────

  async getPlans(): Promise<{ data: Plan[]; total: number }> {
    return this.request("/plans", {
      revalidate: 3600,
      tags: ["plans"],
    });
  }

  async getPlan(id: number): Promise<ApiResponse<Plan>> {
    return this.request(`/plans/${id}`, {
      revalidate: 3600,
      tags: ["plans"],
    });
  }

  // ─── Content ───────────────────────────────────────────────

  async getContentList(params?: {
    page?: number;
    per_page?: number;
    topic?: string;
    tier?: string;
    search?: string;
  }): Promise<ApiListResponse<ContentItem>> {
    const searchParams = new URLSearchParams();
    if (params?.page) searchParams.set("page", String(params.page));
    if (params?.per_page)
      searchParams.set("per_page", String(params.per_page));
    if (params?.topic) searchParams.set("topic", params.topic);
    if (params?.tier) searchParams.set("tier", params.tier);
    if (params?.search) searchParams.set("search", params.search);

    const query = searchParams.toString();
    return this.request(`/content${query ? `?${query}` : ""}`, {
      revalidate: 60,
      tags: ["content"],
    });
  }

  async getContent(
    slug: string,
    token?: string
  ): Promise<ApiResponse<ContentItem>> {
    return this.request(`/content/${slug}`, {
      token,
      cache: token ? "no-store" : undefined,
      revalidate: token ? undefined : 300,
      tags: ["content"],
    });
  }

  // ─── Subscriptions ────────────────────────────────────────

  async getCurrentSubscription(
    token: string
  ): Promise<{ data: Subscription | null }> {
    return this.request("/subscriptions/current", {
      token,
      cache: "no-store",
    });
  }

  async createCheckout(
    planId: number,
    token: string
  ): Promise<CheckoutResponse> {
    return this.request("/subscriptions/checkout", {
      method: "POST",
      body: { plan_id: planId },
      token,
    });
  }

  async createPortalSession(token: string): Promise<PortalResponse> {
    return this.request("/subscriptions/portal", {
      method: "POST",
      token,
    });
  }

  async cancelSubscription(token: string): Promise<{ message: string }> {
    return this.request("/subscriptions/cancel", {
      method: "POST",
      token,
    });
  }
}

/**
 * Custom error class for API errors.
 */
export class ApiError extends Error {
  status: number;
  code: string;

  constructor(message: string, status: number, code: string = "unknown") {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
  }
}

/**
 * Singleton API client instance.
 */
export const wp = new WordPressClient(WP_API_URL);
