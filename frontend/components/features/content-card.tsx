import Link from "next/link";
import Image from "next/image";
import { Card } from "@/components/ui/card";
import { TierBadge } from "@/components/ui/badge";
import type { ContentItem } from "@/types";

interface ContentCardProps {
  content: ContentItem;
}

export function ContentCard({ content }: ContentCardProps) {
  return (
    <Link href={`/content/${content.slug}`}>
      <Card hover className="flex h-full flex-col overflow-hidden p-0">
        {/* Featured Image */}
        {content.featured_image && (
          <div className="relative aspect-video w-full overflow-hidden">
            <Image
              src={content.featured_image}
              alt={content.title}
              fill
              className="object-cover transition-transform hover:scale-105"
              sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
            />
          </div>
        )}

        <div className="flex flex-1 flex-col p-5">
          {/* Meta row */}
          <div className="mb-2 flex items-center gap-2">
            <TierBadge tier={content.tier} />
            {content.topics[0] && (
              <span className="text-xs text-slate-500">
                {content.topics[0].name}
              </span>
            )}
          </div>

          {/* Title */}
          <h3 className="mb-2 text-base font-semibold text-slate-900 line-clamp-2">
            {content.title}
          </h3>

          {/* Excerpt */}
          <p className="mb-4 flex-1 text-sm text-slate-500 line-clamp-3">
            {content.excerpt}
          </p>

          {/* Footer */}
          <div className="flex items-center justify-between border-t border-slate-100 pt-3">
            {content.author && (
              <div className="flex items-center gap-2">
                <Image
                  src={content.author.avatar}
                  alt={content.author.name}
                  width={24}
                  height={24}
                  className="rounded-full"
                />
                <span className="text-xs text-slate-600">
                  {content.author.name}
                </span>
              </div>
            )}
            <span className="text-xs text-slate-400">
              {content.read_time} min read
            </span>
          </div>
        </div>
      </Card>
    </Link>
  );
}
