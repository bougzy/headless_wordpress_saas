import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { Card, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { getCurrentUser } from "@/lib/auth";
import { TIER_LABELS } from "@/lib/constants";
import { TierBadge } from "@/components/ui/badge";

export const metadata: Metadata = {
  title: "Settings",
  description: "Manage your account settings.",
};

export default async function SettingsPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login");

  return (
    <main className="bg-slate-50 py-12">
      <div className="container-page max-w-3xl">
        <h1 className="mb-8 text-2xl font-bold text-slate-900">
          Account Settings
        </h1>

        {/* Profile Info */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Profile</CardTitle>
            <CardDescription>Your account information.</CardDescription>
          </CardHeader>

          <dl className="space-y-4 text-sm">
            <div className="flex justify-between">
              <dt className="text-slate-500">Email</dt>
              <dd className="font-medium text-slate-900">{user.email}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-slate-500">Name</dt>
              <dd className="font-medium text-slate-900">
                {user.first_name} {user.last_name}
              </dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-slate-500">Member since</dt>
              <dd className="text-slate-700">
                {new Date(user.created_at).toLocaleDateString("en-US", {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })}
              </dd>
            </div>
          </dl>
        </Card>

        {/* Subscription */}
        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Subscription</CardTitle>
            <CardDescription>Your current plan and billing.</CardDescription>
          </CardHeader>

          <div className="flex items-center gap-3">
            <span className="text-sm text-slate-600">Current plan:</span>
            <TierBadge tier={user.tier} />
            <span className="text-sm font-medium text-slate-900">
              {TIER_LABELS[user.tier] ?? "Free"}
            </span>
          </div>

          {user.subscription && (
            <div className="mt-4 space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-slate-500">Status</span>
                <span className="capitalize text-green-600">
                  {user.subscription.status}
                </span>
              </div>
              {user.subscription.current_period_end > 0 && (
                <div className="flex justify-between">
                  <span className="text-slate-500">Next billing date</span>
                  <span className="text-slate-700">
                    {new Date(
                      user.subscription.current_period_end * 1000
                    ).toLocaleDateString()}
                  </span>
                </div>
              )}
            </div>
          )}

          <div className="mt-4">
            <form action="/api/auth" method="POST" className="inline">
              <input type="hidden" name="action" value="portal" />
              <button
                type="submit"
                className="text-sm font-medium text-brand-600 hover:text-brand-700"
              >
                Manage billing &rarr;
              </button>
            </form>
          </div>
        </Card>

        {/* Danger Zone */}
        <Card className="border-red-200">
          <CardHeader>
            <CardTitle className="text-red-600">Danger Zone</CardTitle>
            <CardDescription>Irreversible account actions.</CardDescription>
          </CardHeader>

          <p className="text-sm text-slate-500">
            To delete your account or cancel your subscription, please contact
            support. We&apos;ll process your request within 24 hours.
          </p>
        </Card>
      </div>
    </main>
  );
}
