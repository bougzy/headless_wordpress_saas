import { NextRequest, NextResponse } from "next/server";
import { revalidatePath } from "next/cache";
import { REVALIDATION_SECRET } from "@/lib/constants";

/**
 * POST /api/revalidate
 *
 * On-demand ISR revalidation endpoint.
 * Called by WordPress when content changes (via RevalidationDispatcher).
 *
 * Expects: { secret: string, paths: string[] } or { secret: string, path: string }
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { secret, paths, path } = body;

    if (secret !== REVALIDATION_SECRET) {
      return NextResponse.json(
        { error: "Invalid revalidation secret" },
        { status: 401 }
      );
    }

    const pathsToRevalidate: string[] = paths || (path ? [path] : []);

    if (pathsToRevalidate.length === 0) {
      return NextResponse.json(
        { error: "No paths provided" },
        { status: 400 }
      );
    }

    for (const p of pathsToRevalidate) {
      revalidatePath(p);
    }

    return NextResponse.json({
      revalidated: true,
      paths: pathsToRevalidate,
      timestamp: Date.now(),
    });
  } catch (error: any) {
    return NextResponse.json(
      { error: "Revalidation failed", detail: error?.message },
      { status: 500 }
    );
  }
}
