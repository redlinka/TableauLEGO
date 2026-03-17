import {
  BoardState,
  ImageRebuildMetrics,
  PuzzleTarget,
  ScoreResult
} from "@games-platform/game-contracts";

export interface ImageRebuildScoreContext {
  invalidAttempts: number;
  expiredTurns: number;
  accumulatedTimeBonus: number;
}

function roundRatio(value: number): number {
  return Number(value.toFixed(4));
}

export function calculateImageRebuildMetrics(
  board: BoardState,
  puzzle: PuzzleTarget,
  context: Pick<ImageRebuildScoreContext, "invalidAttempts" | "expiredTurns">
): ImageRebuildMetrics {
  const totalCells = puzzle.gridWidth * puzzle.gridHeight;
  let occupiedCells = 0;
  let matchedCells = 0;
  let mismatchedCells = 0;

  for (let y = 0; y < puzzle.gridHeight; y += 1) {
    for (let x = 0; x < puzzle.gridWidth; x += 1) {
      const placedColor = board.cells[y]?.[x] ?? null;
      const targetColor = puzzle.cells[y]?.[x];

      if (placedColor === null) {
        continue;
      }

      occupiedCells += 1;

      if (placedColor === targetColor) {
        matchedCells += 1;
      } else {
        mismatchedCells += 1;
      }
    }
  }

  const emptyCells = totalCells - occupiedCells;
  const coverageRatio = totalCells > 0 ? roundRatio(occupiedCells / totalCells) : 0;
  const accuracyRatio =
    occupiedCells > 0 ? roundRatio(matchedCells / occupiedCells) : 0;

  return {
    totalCells,
    occupiedCells,
    matchedCells,
    mismatchedCells,
    emptyCells,
    coverageRatio,
    accuracyRatio,
    invalidAttempts: context.invalidAttempts,
    expiredTurns: context.expiredTurns
  };
}

export function calculateImageRebuildScore(
  metrics: ImageRebuildMetrics,
  context: ImageRebuildScoreContext
): ScoreResult {
  const placementPoints = metrics.occupiedCells * 2;
  const objectivePoints =
    metrics.matchedCells * 10 +
    Math.round(metrics.coverageRatio * 40) +
    Math.round(metrics.accuracyRatio * 80);
  const penalties =
    metrics.mismatchedCells * 6 +
    context.invalidAttempts * 3 +
    context.expiredTurns * 4;
  const timeBonus = context.accumulatedTimeBonus;
  const score = Math.max(0, placementPoints + objectivePoints + timeBonus - penalties);

  return {
    score,
    accuracy: metrics.accuracyRatio,
    timeBonus,
    breakdown: {
      placementPoints,
      objectivePoints,
      lineClearPoints: 0,
      comboPoints: 0,
      timeBonus,
      penalties
    },
    metadata: {
      formulaVersion: "image_rebuild_v1",
      totalCells: metrics.totalCells,
      occupiedCells: metrics.occupiedCells,
      matchedCells: metrics.matchedCells,
      mismatchedCells: metrics.mismatchedCells,
      emptyCells: metrics.emptyCells,
      coverageRatio: metrics.coverageRatio,
      accuracyRatio: metrics.accuracyRatio,
      invalidAttempts: context.invalidAttempts,
      expiredTurns: context.expiredTurns
    }
  };
}
