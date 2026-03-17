import {
  LineBreakerLineClearStats,
  LineBreakerMetrics
} from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import { calculateLineBreakerScore } from "../src/domain/line-breaker/score.js";

describe("line_breaker score", () => {
  it("computes the deterministic cumulative score breakdown", () => {
    const lineClearStats: LineBreakerLineClearStats = {
      totalLinesCleared: 3,
      horizontalLinesCleared: 2,
      verticalLinesCleared: 1,
      simultaneousClearTurns: 1,
      maxLinesSingleTurn: 2,
      totalClearedCells: 29,
      currentCombo: 1,
      maxCombo: 3,
      comboBonusTotal: 30,
      multiLineBonusTotal: 40
    };
    const metrics: LineBreakerMetrics = {
      occupiedCells: 14,
      totalPlacedCells: 18,
      placementsAccepted: 5,
      invalidAttempts: 2,
      expiredTurns: 1,
      lastClearedLines: [],
      lastClearedCellCount: 0
    };

    const score = calculateLineBreakerScore({
      lineClearStats,
      metrics,
      accumulatedTimeBonus: 6
    });

    expect(score.score).toBe(368);
    expect(score.linesCleared).toBe(3);
    expect(score.breakdown).toEqual({
      placementPoints: 36,
      objectivePoints: 156,
      lineClearPoints: 150,
      comboPoints: 30,
      timeBonus: 6,
      penalties: 10
    });
    expect(score.metadata).toMatchObject({
      formulaVersion: "line_breaker_v1",
      totalLinesCleared: 3,
      maxCombo: 3
    });
  });
});
