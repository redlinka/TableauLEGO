import { describe, expect, it } from "vitest";
import {
  calculateLoyaltyReward,
  computeExpirationDate,
  LoyaltyRewardInput
} from "../src/domain/common/loyalty.js";
import { GameMode, GameType } from "@games-platform/game-contracts";

describe("loyalty expiration and policy config", () => {
  const baseSoloInput: LoyaltyRewardInput = {
    gameType: GameType.ImageRebuild,
    mode: GameMode.Solo,
    outcome: "solo",
    score: 200,
    finishReason: "sequence_exhausted",
    imageMetrics: {
      matchedCells: 50,
      accuracyRatio: 0.8,
      coverageRatio: 0.9
    }
  };

  describe("computeExpirationDate", () => {
    it("returns a date 30 days in the future by default", () => {
      const result = computeExpirationDate();
      const expiresAt = new Date(result);
      const now = new Date();
      const diffDays = (expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

      expect(diffDays).toBeGreaterThan(29.9);
      expect(diffDays).toBeLessThan(30.1);
    });

    it("respects custom expiration days from policy", () => {
      const result = computeExpirationDate({ defaultExpirationDays: 7 });
      const expiresAt = new Date(result);
      const now = new Date();
      const diffDays = (expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

      expect(diffDays).toBeGreaterThan(6.9);
      expect(diffDays).toBeLessThan(7.1);
    });

    it("handles 1-day expiration", () => {
      const result = computeExpirationDate({ defaultExpirationDays: 1 });
      const expiresAt = new Date(result);
      const now = new Date();
      const diffDays = (expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

      expect(diffDays).toBeGreaterThan(0.9);
      expect(diffDays).toBeLessThan(1.1);
    });

    it("handles 365-day expiration", () => {
      const result = computeExpirationDate({ defaultExpirationDays: 365 });
      const expiresAt = new Date(result);
      const now = new Date();
      const diffDays = (expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

      expect(diffDays).toBeGreaterThan(364.9);
      expect(diffDays).toBeLessThan(365.1);
    });

    it("returns a valid ISO string", () => {
      const result = computeExpirationDate();

      expect(() => new Date(result)).not.toThrow();
      expect(new Date(result).toISOString()).toBe(result);
    });
  });

  describe("calculateLoyaltyReward with policyConfig", () => {
    it("includes expiresAt in the reward decision", () => {
      const reward = calculateLoyaltyReward(baseSoloInput);

      expect(reward.expiresAt).toBeDefined();
      expect(typeof reward.expiresAt).toBe("string");
      expect(() => new Date(reward.expiresAt)).not.toThrow();
    });

    it("applies default 30-day expiration when no policyConfig given", () => {
      const reward = calculateLoyaltyReward(baseSoloInput);
      const expiresAt = new Date(reward.expiresAt);
      const now = new Date();
      const diffDays = (expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

      expect(diffDays).toBeGreaterThan(29);
      expect(diffDays).toBeLessThan(31);
    });

    it("applies custom expiration from policyConfig", () => {
      const reward = calculateLoyaltyReward({
        ...baseSoloInput,
        policyConfig: { defaultExpirationDays: 7 }
      });
      const expiresAt = new Date(reward.expiresAt);
      const now = new Date();
      const diffDays = (expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

      expect(diffDays).toBeGreaterThan(6);
      expect(diffDays).toBeLessThan(8);
    });

    it("applies multiplier of 1.5x from policyConfig", () => {
      const baseReward = calculateLoyaltyReward(baseSoloInput);
      const boostedReward = calculateLoyaltyReward({
        ...baseSoloInput,
        policyConfig: { multiplier: 1.5 }
      });

      expect(boostedReward.points).toBeGreaterThan(baseReward.points);
      expect(boostedReward.points).toBe(
        Math.max(
          baseReward.breakdown.minimumApplied,
          Math.round(
            (baseReward.breakdown.participationPoints +
              baseReward.breakdown.performancePoints +
              baseReward.breakdown.outcomePoints +
              baseReward.breakdown.gameBonusPoints +
              baseReward.breakdown.adjustmentPoints) * 1.5
          )
        )
      );
    });

    it("applies multiplier of 2x from policyConfig", () => {
      const baseReward = calculateLoyaltyReward(baseSoloInput);
      const doubledReward = calculateLoyaltyReward({
        ...baseSoloInput,
        policyConfig: { multiplier: 2 }
      });

      expect(doubledReward.points).toBeGreaterThanOrEqual(baseReward.points);
    });

    it("respects minimum floor even with low multiplier", () => {
      const reward = calculateLoyaltyReward({
        ...baseSoloInput,
        policyConfig: { multiplier: 0.1 }
      });

      expect(reward.points).toBeGreaterThanOrEqual(reward.breakdown.minimumApplied);
    });

    it("applies multiplier to duplicate mode", () => {
      const dupInput: LoyaltyRewardInput = {
        gameType: GameType.ImageRebuild,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 300,
        imageMetrics: { matchedCells: 60, accuracyRatio: 0.9, coverageRatio: 0.95 }
      };
      const baseReward = calculateLoyaltyReward(dupInput);
      const boostedReward = calculateLoyaltyReward({
        ...dupInput,
        policyConfig: { multiplier: 2 }
      });

      expect(boostedReward.points).toBeGreaterThan(baseReward.points);
    });

    it("combines multiplier and custom expiration", () => {
      const reward = calculateLoyaltyReward({
        ...baseSoloInput,
        policyConfig: { multiplier: 1.5, defaultExpirationDays: 14 }
      });

      const expiresAt = new Date(reward.expiresAt);
      const now = new Date();
      const diffDays = (expiresAt.getTime() - now.getTime()) / (1000 * 60 * 60 * 24);

      expect(diffDays).toBeGreaterThan(13);
      expect(diffDays).toBeLessThan(15);
      expect(reward.points).toBeGreaterThanOrEqual(6);
    });
  });
});
