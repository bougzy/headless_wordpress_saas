import type { Metadata } from "next";
import Link from "next/link";
import Image from "next/image";
import { notFound } from "next/navigation";
import { Header } from "@/components/layout/header";
import { Footer } from "@/components/layout/footer";
import { TierBadge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { getCurrentUser, getAccessToken } from "@/lib/auth";
import { wp } from "@/lib/wordpress";
import type { ContentItem } from "@/types";

interface ContentDetailPageProps {
  params: { slug: string };
}

export async function generateMetadata({
  params,
}: ContentDetailPageProps): Promise<Metadata> {
  try {
    const { data } = await wp.getContent(params.slug);
    return {
      title: data.title,
      description: data.excerpt,
      openGraph: {
        title: data.title,
        description: data.excerpt,
        type: "article",
        publishedTime: data.published_at,
        modifiedTime: data.updated_at,
        ...(data.featured_image && { images: [data.featured_image] }),
      },
    };
  } catch {
    return { title: "Content Not Found" };
  }
}

export default async function ContentDetailPage({
  params,
}: ContentDetailPageProps) {
  const user = await getCurrentUser();
  const token = getAccessToken();

  let content: ContentItem;
  try {
    const res = await wp.getContent(params.slug, token ?? undefined);
    content = res.data;
  } catch {
    notFound();
  }

  return (
    <>
      <Header user={user} />

      <main className="bg-white py-12">
        <article className="container-page">
          <div className="mx-auto max-w-3xl">
            {/* Breadcrumb */}
            <nav className="mb-6 text-sm text-slate-500" aria-label="Breadcrumb">
              <Link href="/content" className="hover:text-brand-600">
                Content
              </Link>
              <span className="mx-2">/</span>
              <span className="text-slate-900">{content.title}</span>
            </nav>

            {/* Meta */}
            <div className="mb-4 flex flex-wrap items-center gap-3">
              <TierBadge tier={content.tier} />
              {content.topics.map((topic) => (
                <Link
                  key={topic.id}
                  href={`/content?topic=${topic.slug}`}
                  className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-200"
                >
                  {topic.name}
                </Link>
              ))}
              <span className="text-sm text-slate-400">
                {content.read_time} min read
              </span>
            </div>

            {/* Title */}
            <h1 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
              {content.title}
            </h1>

            {/* Author */}
            {content.author && (
              <div className="mt-6 flex items-center gap-3">
                <Image
                  src={content.author.avatar}
                  alt={content.author.name}
                  width={40}
                  height={40}
                  className="rounded-full"
                />
                <div>
                  <p className="text-sm font-medium text-slate-900">
                    {content.author.name}
                  </p>
                  <p className="text-xs text-slate-500">
                    {new Date(content.published_at).toLocaleDateString(
                      "en-US",
                      {
                        year: "numeric",
                        month: "long",
                        day: "numeric",
                      }
                    )}
                  </p>
                </div>
              </div>
            )}

            {/* Featured Image */}
            {content.featured_image && (
              <div className="relative mt-8 aspect-video overflow-hidden rounded-xl">
                <Image
                  src={content.featured_image}
                  alt={content.title}
                  fill
                  className="object-cover"
                  priority
                  sizes="(max-width: 768px) 100vw, 768px"
                />
              </div>
            )}

            {/* Content Body or Gate */}
            {content.has_access && content.body ? (
              <div
                className="prose-content mt-10"
                dangerouslySetInnerHTML={{ __html: content.body }}
              />
            ) : (
              <div className="mt-10 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                <div className="mx-auto max-w-md">
                  <svg
                    className="mx-auto h-12 w-12 text-slate-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={1.5}
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                    />
                  </svg>
                  <h3 className="mt-4 text-lg font-semibold text-slate-900">
                    Premium Content
                  </h3>
                  <p className="mt-2 text-sm text-slate-500">
                    {content.upgrade_message ||
                      "This content requires a paid subscription."}
                  </p>

                  {/* Preview excerpt */}
                  <div className="mt-4 rounded-lg bg-white p-4 text-left text-sm text-slate-600 italic">
                    {content.excerpt}
                  </div>

                  <div className="mt-6">
                    {user ? (
                      <Link href="/pricing">
                        <Button size="lg">Upgrade Your Plan</Button>
                      </Link>
                    ) : (
                      <Link href={`/login?redirect=/content/${content.slug}`}>
                        <Button size="lg">Sign In to Access</Button>
                      </Link>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </article>
      </main>

      <Footer />
    </>
  );
}
