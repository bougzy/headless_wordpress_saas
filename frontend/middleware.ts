import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

/**
 * Next.js Edge Middleware for route protection.
 *
 * - /dashboard and /settings require authentication (access token cookie).
 * - Unauthenticated users are redirected to /login.
 * - Logged-in users accessing /login are redirected to /dashboard.
 */

const PROTECTED_ROUTES = ["/dashboard", "/settings"];
const AUTH_ROUTES = ["/login"];

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const accessToken = request.cookies.get("jenga_access_token")?.value;

  // Protect dashboard routes — redirect to login if no token
  if (PROTECTED_ROUTES.some((route) => pathname.startsWith(route))) {
    if (!accessToken) {
      const loginUrl = new URL("/login", request.url);
      loginUrl.searchParams.set("redirect", pathname);
      return NextResponse.redirect(loginUrl);
    }
  }

  // Redirect logged-in users away from auth pages
  if (AUTH_ROUTES.some((route) => pathname.startsWith(route))) {
    if (accessToken) {
      return NextResponse.redirect(new URL("/dashboard", request.url));
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/dashboard/:path*", "/settings/:path*", "/login"],
};
