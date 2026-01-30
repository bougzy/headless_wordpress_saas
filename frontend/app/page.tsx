import Link from "next/link";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { getCurrentUser } from "@/lib/auth";
import { wp } from "@/lib/wordpress";
import { ContentCard } from "@/components/features/content-card";

export default async function HomePage() {
  const user = await getCurrentUser();

  let recentContent = [];
  try {
    const res = await wp.getContentList({ per_page: 3 });
    recentContent = res.data;
  } catch {
    // Graceful degradation
  }

  return (
    <>
      <Header user={user} />

      <main>
        {/* Hero Section */}
        <section className="relative overflow-hidden bg-gradient-to-b from-brand-50 to-white">
          <div className="container-page py-24 text-center lg:py-32">
            <h1 className="mx-auto max-w-4xl text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
              Expert content,{" "}
              <span className="text-brand-600">delivered</span> to help you
              grow
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg text-slate-600">
              Subscribe to curated articles, in-depth tutorials, and exclusive
              insights from industry experts. Free and premium tiers available.
            </p>
            <div className="mt-10 flex items-center justify-center gap-4">
              <Link
                href="/login?tab=register"
                className="rounded-lg bg-brand-600 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-brand-700"
              >
                Start Reading Free
              </Link>
              <Link
                href="/pricing"
                className="rounded-lg border border-slate-300 px-6 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
              >
                View Pricing
              </Link>
            </div>
          </div>
        </section>

        {/* How it Works */}
        <section className="border-t border-slate-100 bg-white py-20">
          <div className="container-page">
            <h2 className="text-center text-2xl font-bold text-slate-900">
              How it works
            </h2>
            <div className="mt-12 grid gap-8 md:grid-cols-3">
              {[
                {
                  step: "01",
                  title: "Create an account",
                  desc: "Sign up for free and get instant access to all free-tier content.",
                },
                {
                  step: "02",
                  title: "Choose your plan",
                  desc: "Upgrade to Pro or Premium to unlock exclusive articles and resources.",
                },
                {
                  step: "03",
                  title: "Start learning",
                  desc: "Read content from expert creators. New articles published weekly.",
                },
              ].map((item) => (
                <div key={item.step} className="text-center">
                  <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                    {item.step}
                  </div>
                  <h3 className="mt-4 text-lg font-semibold text-slate-900">
                    {item.title}
                  </h3>
                  <p className="mt-2 text-sm text-slate-500">{item.desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Recent Content */}
        {recentContent.length > 0 && (
          <section className="border-t border-slate-100 bg-slate-50 py-20">
            <div className="container-page">
              <div className="flex items-center justify-between">
                <h2 className="text-2xl font-bold text-slate-900">
                  Latest Content
                </h2>
                <Link
                  href="/content"
                  className="text-sm font-medium text-brand-600 hover:text-brand-700"
                >
                  View all &rarr;
                </Link>
              </div>
              <div className="mt-8 grid gap-6 md:grid-cols-3">
                {recentContent.map((item) => (
                  <ContentCard key={item.id} content={item} />
                ))}
              </div>
            </div>
          </section>
        )}

        {/* CTA */}
        <section className="bg-brand-600 py-20">
          <div className="container-page text-center">
            <h2 className="text-3xl font-bold text-white">
              Ready to level up?
            </h2>
            <p className="mx-auto mt-4 max-w-xl text-brand-100">
              Join thousands of professionals who stay ahead with Jenga.
              Start free, upgrade when you&apos;re ready.
            </p>
            <Link
              href="/login?tab=register"
              className="mt-8 inline-block rounded-lg bg-white px-8 py-3 text-sm font-semibold text-brand-600 shadow-sm transition-colors hover:bg-brand-50"
            >
              Get Started Free
            </Link>
          </div>
        </section>
      </main>

      <Footer />
    </>
  );
}
