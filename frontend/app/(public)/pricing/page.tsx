import type { Metadata } from "next";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { PricingCard } from "@/components/features/pricing-card";
import { getCurrentUser } from "@/lib/auth";
import { wp } from "@/lib/wordpress";

export const metadata: Metadata = {
  title: "Pricing",
  description:
    "Choose the plan that fits your needs. Free, Pro, and Premium tiers available.",
};

export default async function PricingPage() {
  const user = await getCurrentUser();

  let plans = [];
  try {
    const res = await wp.getPlans();
    plans = res.data;
  } catch {
    // Fallback empty
  }

  const currentTier = user?.tier ?? 0;

  return (
    <>
      <Header user={user} />

      <main className="bg-white py-20">
        <div className="container-page">
          <div className="text-center">
            <h1 className="text-3xl font-bold text-slate-900 sm:text-4xl">
              Simple, transparent pricing
            </h1>
            <p className="mx-auto mt-4 max-w-2xl text-lg text-slate-500">
              Start free and upgrade as you grow. All plans include access to
              the community and free-tier content.
            </p>
          </div>

          <div className="mx-auto mt-16 grid max-w-5xl gap-8 md:grid-cols-3">
            {plans.map((plan) => (
              <PricingCard
                key={plan.id}
                plan={plan}
                currentTier={currentTier}
                isAuthenticated={!!user}
                featured={plan.tier === 1}
              />
            ))}
          </div>

          {plans.length === 0 && (
            <div className="mt-12 text-center text-slate-500">
              <p>Plans are being configured. Check back soon.</p>
            </div>
          )}

          {/* FAQ */}
          <div className="mx-auto mt-20 max-w-3xl">
            <h2 className="text-center text-2xl font-bold text-slate-900">
              Frequently asked questions
            </h2>
            <dl className="mt-8 space-y-6">
              {[
                {
                  q: "Can I cancel anytime?",
                  a: "Yes. Cancel from your dashboard at any time. You'll retain access until the end of your billing period.",
                },
                {
                  q: "What payment methods are accepted?",
                  a: "We accept all major credit and debit cards through Stripe. All payments are processed securely.",
                },
                {
                  q: "Can I switch plans?",
                  a: "You can upgrade anytime. When upgrading, you'll be charged the prorated difference for the remainder of your billing cycle.",
                },
                {
                  q: "Is there a free trial?",
                  a: "The Free tier gives you permanent access to free content. Upgrade when you're ready for premium content.",
                },
              ].map((faq) => (
                <div key={faq.q} className="border-b border-slate-100 pb-6">
                  <dt className="text-base font-semibold text-slate-900">
                    {faq.q}
                  </dt>
                  <dd className="mt-2 text-sm text-slate-500">{faq.a}</dd>
                </div>
              ))}
            </dl>
          </div>
        </div>
      </main>

      <Footer />
    </>
  );
}
