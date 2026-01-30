import type { Metadata } from "next";
import { APP_NAME } from "@/lib/constants";
import "./globals.css";

export const metadata: Metadata = {
  title: {
    default: `${APP_NAME} — Premium Content Platform`,
    template: `%s | ${APP_NAME}`,
  },
  description:
    "Subscribe to expert-curated content. Access articles, tutorials, and exclusive insights with a Jenga membership.",
  metadataBase: new URL(
    process.env.NEXT_PUBLIC_APP_URL || "http://localhost:3000"
  ),
  openGraph: {
    type: "website",
    siteName: APP_NAME,
    title: `${APP_NAME} — Premium Content Platform`,
    description:
      "Subscribe to expert-curated content. Access articles, tutorials, and exclusive insights.",
  },
  twitter: {
    card: "summary_large_image",
  },
  robots: {
    index: true,
    follow: true,
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body className="min-h-screen bg-white">
        {children}
      </body>
    </html>
  );
}
