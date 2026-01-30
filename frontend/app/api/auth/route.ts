import { NextRequest, NextResponse } from "next/server";
import { wp } from "@/lib/wordpress";
import { setAuthCookies, clearAuthCookies, getAccessToken } from "@/lib/auth";

/**
 * POST /api/auth
 *
 * Proxies authentication actions to the WordPress backend.
 * Actions: login, register, logout, refresh, checkout, portal
 *
 * Auth cookies are set/cleared server-side (httpOnly) so tokens
 * are never exposed to client-side JavaScript.
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const action = body.action;

    switch (action) {
      case "login": {
        const result = await wp.login(body.email, body.password);
        setAuthCookies(result.tokens);
        return NextResponse.json({ user: result.user });
      }

      case "register": {
        const result = await wp.register({
          email: body.email,
          password: body.password,
          first_name: body.first_name,
          last_name: body.last_name,
        });
        setAuthCookies(result.tokens);
        return NextResponse.json({ user: result.user });
      }

      case "logout": {
        clearAuthCookies();
        return NextResponse.redirect(new URL("/", request.url));
      }

      case "refresh": {
        const refreshToken = request.cookies.get("jenga_refresh_token")?.value;
        if (!refreshToken) {
          return NextResponse.json(
            { error: "No refresh token" },
            { status: 401 }
          );
        }
        const result = await wp.refreshTokens(refreshToken);
        setAuthCookies(result.tokens);
        return NextResponse.json({ success: true });
      }

      case "checkout": {
        const token = getAccessToken();
        if (!token) {
          return NextResponse.json(
            { error: "Authentication required" },
            { status: 401 }
          );
        }
        const result = await wp.createCheckout(body.plan_id, token);
        return NextResponse.json(result);
      }

      case "portal": {
        const token = getAccessToken();
        if (!token) {
          return NextResponse.json(
            { error: "Authentication required" },
            { status: 401 }
          );
        }
        const result = await wp.createPortalSession(token);
        return NextResponse.redirect(result.portal_url);
      }

      default:
        return NextResponse.json(
          { error: "Invalid action" },
          { status: 400 }
        );
    }
  } catch (error: any) {
    const status = error?.status || 500;
    const message = error?.message || "Internal server error";
    return NextResponse.json({ error: message }, { status });
  }
}
