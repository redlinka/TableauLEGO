import {
  LineBreakerLineClearStats,
  LineBreakerMetrics
} from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import { calculateLineBreakerScore } from "../src/domain/line-breaker/score.js";

function emptyStats(): LineBreakerLineClearStats {
  return {
    totalLinesCleared: 0,
    horizontalLinesCleared: 0,
    verticalLinesCleared: 0,
    simultaneousClearTurns: 0,
    maxLinesSingleTurn: 0,
    totalClearedCells: 0,
    currentCombo: 0,
    maxCombo: 0,
    comboBonusTotal: 0,
    multiLineBonusTotal: 0
  };
}

function baseMetrics(overrides?: Partial<LineBreakerMetrics>): LineBreakerMetrics {
  return {
    occupiedCells: 0,
    totalPlacedCells: 0,
    placementsAccepted: 0,
    invalidAttempts: 0,
    expiredTurns: 0,
    lastClearedLines: [],
    lastClearedCellCount: 0,
    ...overrides
  };
}

describe("line_breaker score – extended", () => {
  it("returns zero score when nothing has been placed", () => {
    const score = calculateLineBreakerScore({
      lineClearStats: emptyStats(),
      metrics: baseMetrics(),
      accumulatedTimeBonus: 0
    });

    expect(score.score).toBe(0);
    expect(score.linesCleared).toBe(0);
    expect(score.breakdown.placementPoints).toBe(0);
    expect(score.breakdown.objectivePoints).toBe(0);
    expect(score.breakdown.lineClearPoints).toBe(0);
    expect(score.breakdown.comboPoints).toBe(0);
    expect(score.breakdown.penalties).toBe(0);
  });

  it("floors the score at zero when penalties overwhelm points", () => {
    const score = calculateLineBreakerScore({
      lineClearStats: emptyStats(),
      metrics: baseMetrics({
        totalPlacedCells: 1,
        invalidAttempts: 50,
        expiredTurns: 50
      }),
      accumulatedTimeBonus: 0
    });

    expect(score.score).toBe(0);
    expect(score.breakdown.penalties).toBe(50 * 3 + 50 * 4);
  });

  it("calculates placement points as totalPlacedCells × 2", () => {
    const score = calculateLineBreakerScore({
      lineClearStats: emptyStats(),
      metrics: baseMetrics({ totalPlacedCells: 15 }),
      accumulatedTimeBonus: 0
    });

    expect(score.breakdown.placementPoints).toBe(30);
  });

  it("calculates lineClearPoints as totalLinesCleared × 50", () => {
    const score = calculateLineBreakerScore({
      lineClearStats: { ...emptyStats(), totalLinesCleared: 5 },
      metrics: baseMetrics(),
      accumulatedTimeBonus: 0
    });

    expect(score.breakdown.lineClearPoints).toBe(250);
  });

  it("includes comboBonus and multiLineBonus in the total", () => {
    const score = calculateLineBreakerScore({
      lineClearStats: {
        ...emptyStats(),
        totalLinesCleared: 4,
        comboBonusTotal: 45,
        multiLineBonusTotal: 80,
        totalClearedCells: 40
      },
      metrics: baseMetrics({ totalPlacedCells: 20 }),
      accumulatedTimeBonus: 3
    });

    expect(score.breakdown.comboPoints).toBe(45);
    expect(score.breakdown.objectivePoints).toBe(40 * 4 + 80);
    expect(score.breakdown.lineClearPoints).toBe(200);
    expect(score.timeBonus).toBe(3);
    expect(score.score).toBe(40 + 240 + 200 + 45 + 3);
  });

  it("reports formulaVersion in metadata", () => {
    const score = calculateLineBreakerScore({
      lineClearStats: emptyStats(),
      metrics: baseMetrics(),
      accumulatedTimeBonus: 0
    });

    expect(score.metadata.formulaVersion).toBe("line_breaker_v1");
  });

  it("tracks all stat fields in metadata", () => {
    const stats: LineBreakerLineClearStats = {
      totalLinesCleared: 7,
      horizontalLinesCleared: 4,
      verticalLinesCleared: 3,
      simultaneousClearTurns: 2,
      maxLinesSingleTurn: 3,
      totalClearedCells: 55,
      currentCombo: 2,
      maxCombo: 4,
      comboBonusTotal: 60,
      multiLineBonusTotal: 90
    };
    const score = calculateLineBreakerScore({
      lineClearStats: stats,
      metrics: baseMetrics({ totalPlacedCells: 30, placementsAccepted: 10 }),
      accumulatedTimeBonus: 0
    });

    expect(score.metadata.totalLinesCleared).toBe(7);
    expect(score.metadata.horizontalLinesCleared).toBe(4);
    expect(score.metadata.verticalLinesCleared).toBe(3);
    expect(score.metadata.maxCombo).toBe(4);
    expect(score.metadata.totalClearedCells).toBe(55);
  });
});
