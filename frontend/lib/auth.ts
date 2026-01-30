import { cookies } from "next/headers";
import { wp } from "./wordpress";
import type { User, AuthTokens } from "@/types";

const ACCESS_TOKEN_COOKIE = "jenga_access_token";
const REFRESH_TOKEN_COOKIE = "jenga_refresh_token";

/**
 * Server-side authentication utilities.
 *
 * Tokens are stored in httpOnly cookies for security.
 * Access token is short-lived; refresh token is used to obtain new access tokens.
 */

/**
 * Get the current user from the access token cookie.
 * Returns null if not authenticated or token is expired.
 */
export async function getCurrentUser(): Promise<User | null> {
  const cookieStore = cookies();
  const token = cookieStore.get(ACCESS_TOKEN_COOKIE)?.value;

  if (!token) {
    return null;
  }

  try {
    const { user } = await wp.getMe(token);
    return user;
  } catch {
    // Token might be expired — try refreshing
    return tryRefreshAuth();
  }
}

/**
 * Get the access token from cookies (for use in API calls).
 */
export function getAccessToken(): string | null {
  const cookieStore = cookies();
  return cookieStore.get(ACCESS_TOKEN_COOKIE)?.value ?? null;
}

/**
 * Try to refresh authentication using the refresh token.
 */
async function tryRefreshAuth(): Promise<User | null> {
  const cookieStore = cookies();
  const refreshToken = cookieStore.get(REFRESH_TOKEN_COOKIE)?.value;

  if (!refreshToken) {
    return null;
  }

  try {
    const { tokens } = await wp.refreshTokens(refreshToken);
    // Note: In server components, we can't set cookies directly.
    // The refresh flow is handled client-side via the auth API route.
    // This is a fallback for edge cases.
    return null;
  } catch {
    return null;
  }
}

/**
 * Set auth cookies from tokens. Used in API route handlers.
 */
export function setAuthCookies(tokens: AuthTokens): void {
  const cookieStore = cookies();

  cookieStore.set(ACCESS_TOKEN_COOKIE, tokens.access_token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    maxAge: tokens.expires_in,
    path: "/",
  });

  cookieStore.set(REFRESH_TOKEN_COOKIE, tokens.refresh_token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    maxAge: 60 * 60 * 24 * 7, // 7 days
    path: "/",
  });
}

/**
 * Clear auth cookies. Used on logout.
 */
export function clearAuthCookies(): void {
  const cookieStore = cookies();
  cookieStore.delete(ACCESS_TOKEN_COOKIE);
  cookieStore.delete(REFRESH_TOKEN_COOKIE);
}
