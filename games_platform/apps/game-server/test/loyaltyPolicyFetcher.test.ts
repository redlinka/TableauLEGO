import { describe, expect, it } from "vitest";
import { toLoyaltyPolicyConfig, PhpLoyaltyPolicy } from "../src/domain/common/loyaltyPolicyFetcher.js";

describe("loyaltyPolicyFetcher", () => {
  describe("toLoyaltyPolicyConfig", () => {
    it("returns defaults when policy is null", () => {
      const config = toLoyaltyPolicyConfig(null);

      expect(config.defaultExpirationDays).toBe(30);
      expect(config.multiplier).toBe(1);
    });

    it("extracts expiration days from policy", () => {
      const policy: PhpLoyaltyPolicy = {
        version: "policy_v2",
        updatedAt: new Date().toISOString(),
        expiration: { defaultDays: 14, description: "test" },
        tiers: [{ points: 200, discountPercent: 5 }],
        maxDiscountPercent: 50,
        temporalRules: [],
        currentMultiplier: {
          value: 1,
          activeRule: null,
          serverTime: new Date().toISOString(),
          hour: 12,
          dayOfWeek: 3
        }
      };

      const config = toLoyaltyPolicyConfig(policy);

      expect(config.defaultExpirationDays).toBe(14);
      expect(config.multiplier).toBe(1);
    });

    it("extracts multiplier from policy", () => {
      const policy: PhpLoyaltyPolicy = {
        version: "policy_v2",
        updatedAt: new Date().toISOString(),
        expiration: { defaultDays: 30, description: "test" },
        tiers: [{ points: 200, discountPercent: 5 }],
        maxDiscountPercent: 50,
        temporalRules: [],
        currentMultiplier: {
          value: 2.0,
          activeRule: "weekend_bonus",
          serverTime: new Date().toISOString(),
          hour: 14,
          dayOfWeek: 6
        }
      };

      const config = toLoyaltyPolicyConfig(policy);

      expect(config.multiplier).toBe(2.0);
    });

    it("handles happy hour multiplier", () => {
      const policy: PhpLoyaltyPolicy = {
        version: "policy_v2",
        updatedAt: new Date().toISOString(),
        expiration: { defaultDays: 30, description: "test" },
        tiers: [],
        maxDiscountPercent: 50,
        temporalRules: [
          {
            id: "happy_hour_evening",
            label: "Happy Hour du soir",
            hours: [18, 19, 20, 21],
            days: null,
            multiplier: 1.5
          }
        ],
        currentMultiplier: {
          value: 1.5,
          activeRule: "happy_hour_evening",
          serverTime: new Date().toISOString(),
          hour: 19,
          dayOfWeek: 2
        }
      };

      const config = toLoyaltyPolicyConfig(policy);

      expect(config.multiplier).toBe(1.5);
      expect(config.defaultExpirationDays).toBe(30);
    });

    it("handles lunch break multiplier", () => {
      const policy: PhpLoyaltyPolicy = {
        version: "policy_v2",
        updatedAt: new Date().toISOString(),
        expiration: { defaultDays: 30, description: "test" },
        tiers: [],
        maxDiscountPercent: 50,
        temporalRules: [],
        currentMultiplier: {
          value: 1.25,
          activeRule: "lunch_break",
          serverTime: new Date().toISOString(),
          hour: 12,
          dayOfWeek: 3
        }
      };

      const config = toLoyaltyPolicyConfig(policy);

      expect(config.multiplier).toBe(1.25);
    });
  });
});
