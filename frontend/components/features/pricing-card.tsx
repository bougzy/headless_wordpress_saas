"use client";

import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { TierBadge } from "@/components/ui/badge";
import type { Plan } from "@/types";
import { useState } from "react";

interface PricingCardProps {
  plan: Plan;
  currentTier: number;
  isAuthenticated: boolean;
  featured?: boolean;
}

export function PricingCard({
  plan,
  currentTier,
  isAuthenticated,
  featured = false,
}: PricingCardProps) {
  const [loading, setLoading] = useState(false);
  const isCurrentPlan = currentTier === plan.tier;
  const isDowngrade = currentTier > plan.tier;

  async function handleSubscribe() {
    if (!isAuthenticated) {
      window.location.href = `/login?redirect=/pricing`;
      return;
    }

    if (plan.tier === 0 || isCurrentPlan) return;

    setLoading(true);
    try {
      const res = await fetch("/api/auth", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "checkout", plan_id: plan.id }),
      });
      const data = await res.json();
      if (data.checkout_url) {
        window.location.href = data.checkout_url;
      }
    } catch (err) {
      console.error("Checkout error:", err);
    } finally {
      setLoading(false);
    }
  }

  const priceDisplay =
    plan.price === 0
      ? "Free"
      : `$${(plan.price / 100).toFixed(0)}`;

  return (
    <Card
      className={
        featured
          ? "relative border-2 border-brand-500 ring-1 ring-brand-500"
          : ""
      }
    >
      {featured && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-3 py-0.5 text-xs font-medium text-white">
          Most Popular
        </div>
      )}

      <div className="mb-4">
        <TierBadge tier={plan.tier} />
      </div>

      <h3 className="text-xl font-bold text-slate-900">{plan.name}</h3>

      <div className="mt-4 flex items-baseline gap-1">
        <span className="text-4xl font-bold text-slate-900">
          {priceDisplay}
        </span>
        {plan.price > 0 && (
          <span className="text-sm text-slate-500">/month</span>
        )}
      </div>

      <p className="mt-3 text-sm text-slate-500">{plan.description}</p>

      <ul className="mt-6 space-y-3" role="list">
        {plan.features.map((feature, i) => (
          <li key={i} className="flex items-start gap-2 text-sm text-slate-700">
            <svg
              className="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-500"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
            {feature}
          </li>
        ))}
      </ul>

      <div className="mt-8">
        <Button
          onClick={handleSubscribe}
          loading={loading}
          variant={featured ? "primary" : "outline"}
          className="w-full"
          disabled={isCurrentPlan || isDowngrade}
        >
          {isCurrentPlan
            ? "Current Plan"
            : isDowngrade
              ? "Current plan is higher"
              : plan.price === 0
                ? "Get Started Free"
                : "Subscribe"}
        </Button>
      </div>
    </Card>
  );
}
