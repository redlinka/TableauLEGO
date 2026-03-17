import { GameMode, GameType } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import { calculateLoyaltyReward } from "../src/domain/common/loyalty.js";

describe("loyalty reward formula", () => {
  it("keeps solo rewards deterministic with a minimum floor", () => {
    const reward = calculateLoyaltyReward({
      gameType: GameType.ImageRebuild,
      mode: GameMode.Solo,
      outcome: "solo",
      score: 0,
      finishReason: "sequence_exhausted",
      imageMetrics: {
        matchedCells: 0,
        accuracyRatio: 0,
        coverageRatio: 0
      }
    });

    expect(reward.formulaVersion).toBe("loyalty_v1");
    expect(reward.points).toBe(10);
    expect(reward.reason).toBe("image_rebuild_solo_completion");
    expect(reward.breakdown.minimumApplied).toBe(6);
  });

  it("orders duplicate outcomes win > draw > loss > forfeit", () => {
    const baseInput = {
      gameType: GameType.LineBreaker,
      mode: GameMode.Duplicate2P as const,
      score: 320,
      lineMetrics: {
        totalLinesCleared: 6,
        maxCombo: 3
      }
    };

    const win = calculateLoyaltyReward({
      ...baseInput,
      outcome: "win"
    });
    const draw = calculateLoyaltyReward({
      ...baseInput,
      outcome: "draw"
    });
    const loss = calculateLoyaltyReward({
      ...baseInput,
      outcome: "loss"
    });
    const forfeit = calculateLoyaltyReward({
      ...baseInput,
      outcome: "forfeit",
      score: 0,
      lineMetrics: {
        totalLinesCleared: 0,
        maxCombo: 0
      }
    });

    expect(win.points).toBeGreaterThan(draw.points);
    expect(draw.points).toBeGreaterThan(loss.points);
    expect(loss.points).toBeGreaterThan(forfeit.points);
    expect(forfeit.points).toBe(14);
  });

  it("rewards better line-breaker performance above participation only", () => {
    const conservative = calculateLoyaltyReward({
      gameType: GameType.LineBreaker,
      mode: GameMode.Solo,
      outcome: "solo",
      score: 120,
      finishReason: "sequence_exhausted",
      lineMetrics: {
        totalLinesCleared: 1,
        maxCombo: 1
      }
    });
    const strong = calculateLoyaltyReward({
      gameType: GameType.LineBreaker,
      mode: GameMode.Solo,
      outcome: "solo",
      score: 980,
      finishReason: "completed",
      lineMetrics: {
        totalLinesCleared: 10,
        maxCombo: 6
      }
    });

    expect(strong.points).toBeGreaterThan(conservative.points);
    expect(strong.breakdown.gameBonusPoints).toBeGreaterThan(
      conservative.breakdown.gameBonusPoints
    );
  });
});
