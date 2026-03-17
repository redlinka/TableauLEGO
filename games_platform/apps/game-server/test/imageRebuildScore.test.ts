import { BoardState, PuzzleTarget } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import {
  calculateImageRebuildMetrics,
  calculateImageRebuildScore
} from "../src/domain/image-rebuild/score.js";

describe("image_rebuild score", () => {
  it("calculates deterministic metrics and score breakdown", () => {
    const puzzle: PuzzleTarget = {
      id: "score_test",
      name: "Score Test",
      gridWidth: 2,
      gridHeight: 2,
      palette: ["red", "blue", "yellow", "green", "black"],
      cells: [
        ["red", "blue"],
        ["yellow", "green"]
      ],
      previewImage: "score_test.png",
      sourceImage: "score_test.png",
      difficulty: "medium"
    };
    const board: BoardState = {
      width: 2,
      height: 2,
      occupiedCount: 3,
      cells: [
        ["red", "blue"],
        ["black", null]
      ]
    };

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 2,
      expiredTurns: 1
    });
    const score = calculateImageRebuildScore(metrics, {
      invalidAttempts: 2,
      expiredTurns: 1,
      accumulatedTimeBonus: 5
    });

    expect(metrics).toEqual({
      totalCells: 4,
      occupiedCells: 3,
      matchedCells: 2,
      mismatchedCells: 1,
      emptyCells: 1,
      coverageRatio: 0.75,
      accuracyRatio: 0.6667,
      invalidAttempts: 2,
      expiredTurns: 1
    });
    expect(score.score).toBe(98);
    expect(score.breakdown).toEqual({
      placementPoints: 6,
      objectivePoints: 103,
      lineClearPoints: 0,
      comboPoints: 0,
      timeBonus: 5,
      penalties: 16
    });
    expect(score.metadata).toMatchObject({
      formulaVersion: "image_rebuild_v1",
      matchedCells: 2,
      invalidAttempts: 2,
      expiredTurns: 1
    });
  });
});
