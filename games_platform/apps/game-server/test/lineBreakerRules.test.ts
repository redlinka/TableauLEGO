import { INITIAL_BRICK_SHAPES } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import { applyPlacement, createEmptyBoard } from "../src/domain/common/boardEngine.js";
import {
  clearCompletedLines,
  detectCompletedMonochromeLines
} from "../src/domain/line-breaker/lineRules.js";

function getShape(shapeId: string) {
  const shape = INITIAL_BRICK_SHAPES.find((entry) => entry.shapeId === shapeId);

  if (!shape) {
    throw new Error(`Missing shape '${shapeId}' in test catalog.`);
  }

  return shape;
}

describe("line breaker rules", () => {
  it("detects a complete horizontal monochrome line", () => {
    const board = createEmptyBoard(4, 3);

    for (let x = 0; x < 4; x += 1) {
      board.cells[1][x].color = "blue";
      board.occupiedCount += 1;
    }

    const lines = detectCompletedMonochromeLines(board);

    expect(lines).toEqual([
      {
        orientation: "row",
        index: 1,
        color: "blue",
        cellCount: 4,
        cells: [
          { x: 0, y: 1 },
          { x: 1, y: 1 },
          { x: 2, y: 1 },
          { x: 3, y: 1 }
        ]
      }
    ]);
  });

  it("detects a complete vertical monochrome line", () => {
    const board = createEmptyBoard(3, 4);

    for (let y = 0; y < 4; y += 1) {
      board.cells[y][2].color = "yellow";
      board.occupiedCount += 1;
    }

    const lines = detectCompletedMonochromeLines(board);

    expect(lines).toEqual([
      {
        orientation: "column",
        index: 2,
        color: "yellow",
        cellCount: 4,
        cells: [
          { x: 2, y: 0 },
          { x: 2, y: 1 },
          { x: 2, y: 2 },
          { x: 2, y: 3 }
        ]
      }
    ]);
  });

  it("clears multiple lines simultaneously without double-removing intersections", () => {
    const board = createEmptyBoard(3, 3);

    for (let x = 0; x < 3; x += 1) {
      board.cells[0][x].color = "green";
      board.occupiedCount += 1;
    }

    for (let y = 1; y < 3; y += 1) {
      board.cells[y][0].color = "green";
      board.occupiedCount += 1;
    }

    const lines = detectCompletedMonochromeLines(board);
    const cleared = clearCompletedLines({
      board,
      lines
    });

    expect(lines).toHaveLength(2);
    expect(cleared.clearedCellCount).toBe(5);
    expect(cleared.board.occupiedCount).toBe(0);
    expect(cleared.board.cells[0][0].color).toBeNull();
    expect(cleared.board.cells[1][0].color).toBeNull();
    expect(cleared.board.cells[0][2].color).toBeNull();
  });

  it("keeps remaining cells after a line removes only part of a multi-cell brick", () => {
    const shape = getShape("l_corner_3");
    const board = applyPlacement({
      board: createEmptyBoard(4, 3),
      shape,
      offer: {
        offerId: "offer_l",
        shapeId: shape.shapeId,
        color: "red",
        issuedAtTurn: 1,
        sequenceIndex: 0
      },
      rotation: 0,
      anchorX: 0,
      anchorY: 1,
      turn: 1
    });

    board.cells[1][1].color = "red";
    board.cells[1][2].color = "red";
    board.cells[1][3].color = "red";
    board.occupiedCount += 3;

    const lines = detectCompletedMonochromeLines(board);
    const cleared = clearCompletedLines({
      board,
      lines
    });

    expect(lines.map((line) => `${line.orientation}:${line.index}`)).toEqual(["row:1"]);
    expect(cleared.board.cells[2][0].color).toBe("red");
    expect(cleared.board.cells[2][1].color).toBe("red");
    expect(cleared.board.cells[1][0].color).toBeNull();
    expect(cleared.board.cells[1][1].color).toBeNull();
    expect(cleared.board.cells[1][2].color).toBeNull();
    expect(cleared.board.cells[1][3].color).toBeNull();
  });
});
