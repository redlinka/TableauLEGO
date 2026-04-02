import { LoyaltyPolicyConfig } from "./loyalty.js";

export interface PhpLoyaltyPolicy {
  version: string;
  updatedAt: string;
  expiration: {
    defaultDays: number;
    description: string;
  };
  tiers: { points: number; discountPercent: number }[];
  maxDiscountPercent: number;
  temporalRules: {
    id: string;
    label: string;
    hours: number[] | null;
    days: number[] | null;
    multiplier: number;
  }[];
  currentMultiplier: {
    value: number;
    activeRule: string | null;
    serverTime: string;
    hour: number;
    dayOfWeek: number;
  };
}

const CACHE_TTL_MS = 5 * 60 * 1000;

let cachedPolicy: PhpLoyaltyPolicy | null = null;
let cachedAt = 0;

/**
 * Fetch the loyalty policy JSON from the PHP backend.
 * Cached for 5 minutes to avoid hammering the PHP server.
 */
export async function fetchLoyaltyPolicy(
  phpApiUrl: string
): Promise<PhpLoyaltyPolicy | null> {
  if (cachedPolicy && Date.now() - cachedAt < CACHE_TTL_MS) {
    return cachedPolicy;
  }

  try {
    const url = `${phpApiUrl}/api/loyalty_policy.php`;
    const response = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(5_000)
    });

    if (!response.ok) {
      return cachedPolicy;
    }

    cachedPolicy = (await response.json()) as PhpLoyaltyPolicy;
    cachedAt = Date.now();
    return cachedPolicy;
  } catch {
    return cachedPolicy;
  }
}

/**
 * Convert the PHP policy into the config format used by calculateLoyaltyReward.
 */
export function toLoyaltyPolicyConfig(
  policy: PhpLoyaltyPolicy | null
): LoyaltyPolicyConfig {
  if (!policy) {
    return { defaultExpirationDays: 30, multiplier: 1 };
  }

  return {
    defaultExpirationDays: policy.expiration.defaultDays,
    multiplier: policy.currentMultiplier.value
  };
}
