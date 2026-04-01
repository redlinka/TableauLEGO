import {
  GameMode,
  GameType,
  LoyaltyEntryType
} from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import { calculateLoyaltyReward } from "../src/domain/common/loyalty.js";

describe("loyalty reward formula – extended", () => {
  describe("solo image_rebuild", () => {
    it("applies the abandonment penalty of -4 points", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 200,
        finishReason: "abandoned",
        imageMetrics: {
          matchedCells: 10,
          accuracyRatio: 0.5,
          coverageRatio: 0.5
        }
      });

      expect(reward.breakdown.adjustmentPoints).toBe(-4);
      expect(reward.points).toBeGreaterThanOrEqual(6);
    });

    it("enforces the solo minimum floor of 6 even with zero everything", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 0,
        finishReason: "abandoned",
        imageMetrics: {
          matchedCells: 0,
          accuracyRatio: 0,
          coverageRatio: 0
        }
      });

      expect(reward.points).toBe(6);
      expect(reward.breakdown.minimumApplied).toBe(6);
    });

    it("rewards high performance with more points", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 800,
        finishReason: "completed",
        imageMetrics: {
          matchedCells: 80,
          accuracyRatio: 0.95,
          coverageRatio: 0.9
        }
      });

      expect(reward.points).toBeGreaterThan(30);
      expect(reward.breakdown.performancePoints).toBe(Math.min(30, Math.floor(800 / 80)));
      expect(reward.breakdown.gameBonusPoints).toBeGreaterThan(0);
    });

    it("caps performancePoints at 30 for solo", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 10000,
        finishReason: "completed",
        imageMetrics: {
          matchedCells: 100,
          accuracyRatio: 1,
          coverageRatio: 1
        }
      });

      expect(reward.breakdown.performancePoints).toBe(30);
    });

    it("caps gameBonusPoints at 20 for solo image_rebuild", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 500,
        finishReason: "completed",
        imageMetrics: {
          matchedCells: 500,
          accuracyRatio: 1,
          coverageRatio: 1
        }
      });

      expect(reward.breakdown.gameBonusPoints).toBeLessThanOrEqual(20);
    });
  });

  describe("solo line_breaker", () => {
    it("calculates game bonus from lines cleared and combo", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 400,
        finishReason: "completed",
        lineMetrics: {
          totalLinesCleared: 5,
          maxCombo: 4
        }
      });

      const expectedGameBonus = Math.min(20, 5 * 2 + Math.floor(4 / 2));

      expect(reward.breakdown.gameBonusPoints).toBe(expectedGameBonus);
    });

    it("returns zero game bonus when lineMetrics is missing", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 100,
        finishReason: "completed"
      });

      expect(reward.breakdown.gameBonusPoints).toBe(0);
    });
  });

  describe("duplicate outcomes", () => {
    it("gives participation=12 for duplicate", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 100
      });

      expect(reward.breakdown.participationPoints).toBe(12);
    });

    it("gives 18 outcome points for a win", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 100
      });

      expect(reward.breakdown.outcomePoints).toBe(18);
    });

    it("gives 12 outcome points for a draw", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "draw",
        score: 100
      });

      expect(reward.breakdown.outcomePoints).toBe(12);
    });

    it("gives 8 outcome points for a loss", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "loss",
        score: 100
      });

      expect(reward.breakdown.outcomePoints).toBe(8);
    });

    it("gives 2 outcome points for a forfeit", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "forfeit",
        score: 0
      });

      expect(reward.breakdown.outcomePoints).toBe(2);
    });

    it("enforces duplicate forfeit minimum of 2", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "forfeit",
        score: 0
      });

      expect(reward.breakdown.minimumApplied).toBe(2);
      expect(reward.points).toBeGreaterThanOrEqual(2);
    });

    it("enforces duplicate non-forfeit minimum of 8", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "loss",
        score: 0
      });

      expect(reward.breakdown.minimumApplied).toBe(8);
      expect(reward.points).toBeGreaterThanOrEqual(8);
    });

    it("caps performancePoints at 20 for duplicate", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 9999
      });

      expect(reward.breakdown.performancePoints).toBe(20);
    });
  });

  describe("duplicate image_rebuild", () => {
    it("calculates game bonus from accuracyRatio only", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 300,
        imageMetrics: {
          matchedCells: 60,
          accuracyRatio: 0.8,
          coverageRatio: 0.7
        }
      });

      const expectedGameBonus = Math.min(10, Math.floor(0.8 * 10));

      expect(reward.breakdown.gameBonusPoints).toBe(expectedGameBonus);
    });

    it("caps gameBonusPoints at 10 for duplicate image_rebuild", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 500,
        imageMetrics: {
          matchedCells: 100,
          accuracyRatio: 1,
          coverageRatio: 1
        }
      });

      expect(reward.breakdown.gameBonusPoints).toBeLessThanOrEqual(10);
    });
  });

  describe("duplicate line_breaker", () => {
    it("caps gameBonusPoints at 10 for duplicate line_breaker", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 500,
        lineMetrics: {
          totalLinesCleared: 20,
          maxCombo: 10
        }
      });

      expect(reward.breakdown.gameBonusPoints).toBeLessThanOrEqual(10);
    });
  });

  describe("entryType and reason", () => {
    it("returns SessionReward for solo", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 100,
        finishReason: "completed"
      });

      expect(reward.entryType).toBe(LoyaltyEntryType.SessionReward);
      expect(reward.reason).toBe("image_rebuild_solo_completion");
    });

    it("returns DuplicateBonus for duplicate", () => {
      const reward = calculateLoyaltyReward({
        gameType: GameType.LineBreaker,
        mode: GameMode.Duplicate2P,
        outcome: "win",
        score: 100
      });

      expect(reward.entryType).toBe(LoyaltyEntryType.DuplicateBonus);
      expect(reward.reason).toBe("line_breaker_duplicate_win");
    });
  });
});
