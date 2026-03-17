import {
  LineBreakerLineClearStats,
  LineBreakerMetrics,
  ScoreResult
} from "@games-platform/game-contracts";

export function calculateLineBreakerScore(input: {
  lineClearStats: LineBreakerLineClearStats;
  metrics: LineBreakerMetrics;
  accumulatedTimeBonus: number;
}): ScoreResult {
  const placementPoints = input.metrics.totalPlacedCells * 2;
  const objectivePoints =
    input.lineClearStats.totalClearedCells * 4 +
    input.lineClearStats.multiLineBonusTotal;
  const lineClearPoints = input.lineClearStats.totalLinesCleared * 50;
  const comboPoints = input.lineClearStats.comboBonusTotal;
  const penalties = input.metrics.invalidAttempts * 3 + input.metrics.expiredTurns * 4;
  const timeBonus = input.accumulatedTimeBonus;
  const score = Math.max(
    0,
    placementPoints +
      objectivePoints +
      lineClearPoints +
      comboPoints +
      timeBonus -
      penalties
  );

  return {
    score,
    linesCleared: input.lineClearStats.totalLinesCleared,
    timeBonus,
    breakdown: {
      placementPoints,
      objectivePoints,
      lineClearPoints,
      comboPoints,
      timeBonus,
      penalties
    },
    metadata: {
      formulaVersion: "line_breaker_v1",
      totalPlacedCells: input.metrics.totalPlacedCells,
      placementsAccepted: input.metrics.placementsAccepted,
      invalidAttempts: input.metrics.invalidAttempts,
      expiredTurns: input.metrics.expiredTurns,
      totalLinesCleared: input.lineClearStats.totalLinesCleared,
      horizontalLinesCleared: input.lineClearStats.horizontalLinesCleared,
      verticalLinesCleared: input.lineClearStats.verticalLinesCleared,
      simultaneousClearTurns: input.lineClearStats.simultaneousClearTurns,
      maxLinesSingleTurn: input.lineClearStats.maxLinesSingleTurn,
      totalClearedCells: input.lineClearStats.totalClearedCells,
      maxCombo: input.lineClearStats.maxCombo
    }
  };
}
