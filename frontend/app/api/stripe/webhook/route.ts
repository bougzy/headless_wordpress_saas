import { NextRequest, NextResponse } from "next/server";
import { revalidatePath } from "next/cache";

/**
 * POST /api/stripe/webhook
 *
 * Receives Stripe webhook events forwarded from the WordPress backend,
 * or directly from Stripe if configured.
 *
 * Primary purpose: trigger frontend revalidation after payment events.
 * The actual subscription logic is handled by WordPress.
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();

    // Revalidate relevant pages after payment events
    const eventType = body.type;

    const revalidationMap: Record<string, string[]> = {
      "checkout.session.completed": ["/dashboard", "/content", "/pricing"],
      "customer.subscription.updated": ["/dashboard"],
      "customer.subscription.deleted": ["/dashboard", "/pricing"],
      "invoice.payment_succeeded": ["/dashboard"],
      "invoice.payment_failed": ["/dashboard"],
    };

    const pathsToRevalidate = revalidationMap[eventType] || [];

    for (const path of pathsToRevalidate) {
      revalidatePath(path);
    }

    return NextResponse.json({
      received: true,
      revalidated: pathsToRevalidate,
    });
  } catch (error: any) {
    console.error("[Stripe Webhook] Error:", error?.message);
    return NextResponse.json(
      { error: "Webhook processing failed" },
      { status: 500 }
    );
  }
}
