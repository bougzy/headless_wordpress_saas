import { clsx } from "clsx";
import { TIER_COLORS } from "@/lib/constants";

interface BadgeProps {
  tier: number;
  label?: string;
  className?: string;
}

export function TierBadge({ tier, label, className }: BadgeProps) {
  return (
    <span className={clsx("tier-badge", TIER_COLORS[tier] ?? TIER_COLORS[0], className)}>
      {label ?? (tier === 0 ? "Free" : tier === 1 ? "Pro" : "Premium")}
    </span>
  );
}
