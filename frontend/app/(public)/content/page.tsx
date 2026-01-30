import type { Metadata } from "next";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { ContentCard } from "@/components/features/content-card";
import { getCurrentUser } from "@/lib/auth";
import { wp } from "@/lib/wordpress";
import type { ContentItem } from "@/types";

export const metadata: Metadata = {
  title: "Content Library",
  description: "Browse our full library of articles, tutorials, and resources.",
};

interface ContentPageProps {
  searchParams: { page?: string; topic?: string; tier?: string };
}

export default async function ContentPage({ searchParams }: ContentPageProps) {
  const user = await getCurrentUser();
  const page = parseInt(searchParams.page || "1", 10);

  let contentData: { data: ContentItem[]; meta: { total: number; pages: number; current_page: number; per_page: number } } = { data: [], meta: { total: 0, pages: 0, current_page: 1, per_page: 12 } };
  try {
    contentData = await wp.getContentList({
      page,
      per_page: 12,
      topic: searchParams.topic,
      tier: searchParams.tier,
    });
  } catch {
    // Graceful degradation
  }

  const { data: content, meta } = contentData;

  return (
    <>
      <Header user={user} />

      <main className="bg-white py-12">
        <div className="container-page">
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-slate-900">
              Content Library
            </h1>
            <p className="mt-2 text-slate-500">
              {meta.total} articles available. Free and premium content for
              every level.
            </p>
          </div>

          {/* Filter bar */}
          <div className="mb-8 flex flex-wrap items-center gap-2">
            {[
              { label: "All", value: "" },
              { label: "Free", value: "0" },
              { label: "Pro", value: "1" },
              { label: "Premium", value: "2" },
            ].map((filter) => {
              const isActive = (searchParams.tier || "") === filter.value;
              return (
                <a
                  key={filter.value}
                  href={`/content${filter.value ? `?tier=${filter.value}` : ""}`}
                  className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                    isActive
                      ? "bg-brand-600 text-white"
                      : "bg-slate-100 text-slate-600 hover:bg-slate-200"
                  }`}
                >
                  {filter.label}
                </a>
              );
            })}
          </div>

          {/* Content Grid */}
          {content.length > 0 ? (
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {content.map((item) => (
                <ContentCard key={item.id} content={item} />
              ))}
            </div>
          ) : (
            <div className="py-20 text-center text-slate-500">
              <p className="text-lg">No content found.</p>
              <p className="mt-2 text-sm">
                Content is being published. Check back soon.
              </p>
            </div>
          )}

          {/* Pagination */}
          {meta.pages > 1 && (
            <nav className="mt-12 flex items-center justify-center gap-2" aria-label="Content pagination">
              {page > 1 && (
                <a
                  href={`/content?page=${page - 1}${searchParams.tier ? `&tier=${searchParams.tier}` : ""}`}
                  className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                >
                  Previous
                </a>
              )}
              <span className="text-sm text-slate-500">
                Page {meta.current_page} of {meta.pages}
              </span>
              {page < meta.pages && (
                <a
                  href={`/content?page=${page + 1}${searchParams.tier ? `&tier=${searchParams.tier}` : ""}`}
                  className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                >
                  Next
                </a>
              )}
            </nav>
          )}
        </div>
      </main>

      <Footer />
    </>
  );
}
