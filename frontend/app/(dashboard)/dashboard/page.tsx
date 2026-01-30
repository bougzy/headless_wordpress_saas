import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { Card, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { TierBadge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { getCurrentUser, getAccessToken } from "@/lib/auth";
import { wp } from "@/lib/wordpress";
import { TIER_LABELS } from "@/lib/constants";

export const metadata: Metadata = {
  title: "Dashboard",
  description: "Your Jenga dashboard — manage your subscription and content.",
};

export default async function DashboardPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login");

  const token = getAccessToken();

  let subscription = null;
  try {
    if (token) {
      const res = await wp.getCurrentSubscription(token);
      subscription = res.data;
    }
  } catch {
    // Graceful degradation
  }

  const tierLabel = TIER_LABELS[user.tier] ?? "Free";
  const hasActiveSubscription =
    subscription?.status === "active" || subscription?.status === "trialing";

  return (
    <main className="bg-slate-50 py-12">
      <div className="container-page">
        {/* Welcome */}
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900">
            Welcome back, {user.first_name || user.display_name}
          </h1>
          <p className="mt-1 text-slate-500">
            Manage your subscription and access your content.
          </p>
        </div>

        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {/* Current Plan */}
          <Card>
            <CardHeader>
              <CardDescription>Current Plan</CardDescription>
              <CardTitle className="flex items-center gap-2">
                {tierLabel}
                <TierBadge tier={user.tier} />
              </CardTitle>
            </CardHeader>

            {hasActiveSubscription && subscription ? (
              <div className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-slate-500">Status</span>
                  <span className="font-medium capitalize text-green-600">
                    {subscription.status}
                  </span>
                </div>
                {subscription.current_period_end > 0 && (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Renews</span>
                    <span className="text-slate-700">
                      {new Date(
                        subscription.current_period_end * 1000
                      ).toLocaleDateString()}
                    </span>
                  </div>
                )}
                {subscription.plan && (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Price</span>
                    <span className="text-slate-700">
                      ${(subscription.plan.price / 100).toFixed(2)}/mo
                    </span>
                  </div>
                )}
              </div>
            ) : (
              <p className="text-sm text-slate-500">
                You&apos;re on the free plan. Upgrade to access premium content.
              </p>
            )}

            <div className="mt-4">
              {hasActiveSubscription ? (
                <ManageSubscriptionButton />
              ) : (
                <Link href="/pricing">
                  <Button variant="primary" size="sm" className="w-full">
                    Upgrade Plan
                  </Button>
                </Link>
              )}
            </div>
          </Card>

          {/* Quick Stats */}
          <Card>
            <CardHeader>
              <CardDescription>Access Level</CardDescription>
              <CardTitle>Content Access</CardTitle>
            </CardHeader>
            <div className="space-y-2">
              <AccessRow label="Free articles" accessible />
              <AccessRow label="Pro articles" accessible={user.tier >= 1} />
              <AccessRow label="Premium articles" accessible={user.tier >= 2} />
            </div>
          </Card>

          {/* Quick Actions */}
          <Card>
            <CardHeader>
              <CardDescription>Quick Actions</CardDescription>
              <CardTitle>Explore</CardTitle>
            </CardHeader>
            <div className="space-y-2">
              <Link href="/content" className="block">
                <Button variant="outline" size="sm" className="w-full justify-start">
                  Browse Content Library
                </Button>
              </Link>
              <Link href="/settings" className="block">
                <Button variant="outline" size="sm" className="w-full justify-start">
                  Account Settings
                </Button>
              </Link>
            </div>
          </Card>
        </div>
      </div>
    </main>
  );
}

function AccessRow({ label, accessible }: { label: string; accessible: boolean }) {
  return (
    <div className="flex items-center justify-between text-sm">
      <span className="text-slate-600">{label}</span>
      {accessible ? (
        <svg className="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
        </svg>
      ) : (
        <svg className="h-5 w-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      )}
    </div>
  );
}

function ManageSubscriptionButton() {
  return (
    <form action="/api/auth" method="POST">
      <input type="hidden" name="action" value="portal" />
      <Button type="submit" variant="outline" size="sm" className="w-full">
        Manage Subscription
      </Button>
    </form>
  );
}
