import { BoardState, PuzzleTarget } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import {
  calculateImageRebuildMetrics,
  calculateImageRebuildScore
} from "../src/domain/image-rebuild/score.js";

function makePuzzle(cells: (string | null)[][]): PuzzleTarget {
  return {
    id: "test_puzzle",
    name: "Test",
    gridWidth: cells[0].length,
    gridHeight: cells.length,
    palette: ["red", "blue", "green", "yellow", "black"],
    cells: cells as string[][],
    previewImage: "test.png",
    sourceImage: "test.png",
    difficulty: "medium"
  };
}

function makeBoard(cells: (string | null)[][]): BoardState {
  let occupiedCount = 0;

  for (const row of cells) {
    for (const cell of row) {
      if (cell !== null) {
        occupiedCount += 1;
      }
    }
  }

  return {
    width: cells[0].length,
    height: cells.length,
    occupiedCount,
    cells
  };
}

describe("image_rebuild score – extended", () => {
  it("returns zero metrics for a completely empty board", () => {
    const puzzle = makePuzzle([
      ["red", "blue"],
      ["green", "yellow"]
    ]);
    const board = makeBoard([
      [null, null],
      [null, null]
    ]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 0,
      expiredTurns: 0
    });

    expect(metrics.occupiedCells).toBe(0);
    expect(metrics.matchedCells).toBe(0);
    expect(metrics.mismatchedCells).toBe(0);
    expect(metrics.emptyCells).toBe(4);
    expect(metrics.coverageRatio).toBe(0);
    expect(metrics.accuracyRatio).toBe(0);
  });

  it("returns perfect metrics when the board fully matches the puzzle", () => {
    const puzzle = makePuzzle([
      ["red", "blue"],
      ["green", "yellow"]
    ]);
    const board = makeBoard([
      ["red", "blue"],
      ["green", "yellow"]
    ]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 0,
      expiredTurns: 0
    });

    expect(metrics.occupiedCells).toBe(4);
    expect(metrics.matchedCells).toBe(4);
    expect(metrics.mismatchedCells).toBe(0);
    expect(metrics.emptyCells).toBe(0);
    expect(metrics.coverageRatio).toBe(1);
    expect(metrics.accuracyRatio).toBe(1);
  });

  it("returns perfect score with no penalties on a perfect match", () => {
    const puzzle = makePuzzle([
      ["red", "blue"],
      ["green", "yellow"]
    ]);
    const board = makeBoard([
      ["red", "blue"],
      ["green", "yellow"]
    ]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 0,
      expiredTurns: 0
    });
    const score = calculateImageRebuildScore(metrics, {
      invalidAttempts: 0,
      expiredTurns: 0,
      accumulatedTimeBonus: 10
    });

    expect(score.accuracy).toBe(1);
    expect(score.breakdown.penalties).toBe(0);
    expect(score.score).toBeGreaterThan(0);
    expect(score.breakdown.placementPoints).toBe(8);
    expect(score.breakdown.objectivePoints).toBe(40 + 40 + 80);
    expect(score.timeBonus).toBe(10);
  });

  it("penalizes all mismatched cells", () => {
    const puzzle = makePuzzle([
      ["red", "blue"],
      ["green", "yellow"]
    ]);
    const board = makeBoard([
      ["blue", "red"],
      ["yellow", "green"]
    ]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 0,
      expiredTurns: 0
    });

    expect(metrics.matchedCells).toBe(0);
    expect(metrics.mismatchedCells).toBe(4);
    expect(metrics.accuracyRatio).toBe(0);
  });

  it("floors the score at zero when penalties exceed points", () => {
    const puzzle = makePuzzle([["red"]]);
    const board = makeBoard([["blue"]]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 100,
      expiredTurns: 100
    });
    const score = calculateImageRebuildScore(metrics, {
      invalidAttempts: 100,
      expiredTurns: 100,
      accumulatedTimeBonus: 0
    });

    expect(score.score).toBe(0);
    expect(score.breakdown.penalties).toBeGreaterThan(0);
  });

  it("accumulates time bonus in the final score", () => {
    const puzzle = makePuzzle([["red"]]);
    const board = makeBoard([["red"]]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 0,
      expiredTurns: 0
    });
    const withBonus = calculateImageRebuildScore(metrics, {
      invalidAttempts: 0,
      expiredTurns: 0,
      accumulatedTimeBonus: 15
    });
    const withoutBonus = calculateImageRebuildScore(metrics, {
      invalidAttempts: 0,
      expiredTurns: 0,
      accumulatedTimeBonus: 0
    });

    expect(withBonus.score - withoutBonus.score).toBe(15);
  });

  it("counts invalid attempts and expired turns as separate penalty sources", () => {
    const puzzle = makePuzzle([["red"]]);
    const board = makeBoard([["red"]]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 5,
      expiredTurns: 3
    });
    const score = calculateImageRebuildScore(metrics, {
      invalidAttempts: 5,
      expiredTurns: 3,
      accumulatedTimeBonus: 0
    });

    expect(score.breakdown.penalties).toBe(5 * 3 + 3 * 4);
  });

  it("reports formulaVersion in metadata", () => {
    const puzzle = makePuzzle([["red"]]);
    const board = makeBoard([["red"]]);

    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 0,
      expiredTurns: 0
    });
    const score = calculateImageRebuildScore(metrics, {
      invalidAttempts: 0,
      expiredTurns: 0,
      accumulatedTimeBonus: 0
    });

    expect(score.metadata.formulaVersion).toBe("image_rebuild_v1");
  });
});
