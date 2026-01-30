import Link from "next/link";
import { APP_NAME } from "@/lib/constants";

export function Footer() {
  return (
    <footer className="border-t border-slate-200 bg-slate-50">
      <div className="container-page py-12">
        <div className="grid gap-8 md:grid-cols-4">
          {/* Brand */}
          <div className="md:col-span-2">
            <div className="flex items-center gap-2">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 font-bold text-white">
                J
              </div>
              <span className="text-lg font-bold text-slate-900">
                {APP_NAME}
              </span>
            </div>
            <p className="mt-3 max-w-md text-sm text-slate-500">
              Premium content platform powered by WordPress and Next.js.
              Subscribe to access expert-curated articles, tutorials, and
              exclusive insights.
            </p>
          </div>

          {/* Links */}
          <div>
            <h4 className="text-sm font-semibold text-slate-900">Product</h4>
            <ul className="mt-3 space-y-2">
              <li>
                <Link href="/content" className="text-sm text-slate-500 hover:text-slate-700">
                  Content
                </Link>
              </li>
              <li>
                <Link href="/pricing" className="text-sm text-slate-500 hover:text-slate-700">
                  Pricing
                </Link>
              </li>
            </ul>
          </div>

          <div>
            <h4 className="text-sm font-semibold text-slate-900">Legal</h4>
            <ul className="mt-3 space-y-2">
              <li>
                <span className="text-sm text-slate-500">Privacy Policy</span>
              </li>
              <li>
                <span className="text-sm text-slate-500">Terms of Service</span>
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-8 border-t border-slate-200 pt-8 text-center text-sm text-slate-400">
          &copy; {new Date().getFullYear()} {APP_NAME}. Built with WordPress &amp; Next.js.
        </div>
      </div>
    </footer>
  );
}
