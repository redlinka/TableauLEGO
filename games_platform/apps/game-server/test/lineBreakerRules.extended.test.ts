import { describe, expect, it } from "vitest";
import { createEmptyBoard } from "../src/domain/common/boardEngine.js";
import {
  clearCompletedLines,
  detectCompletedMonochromeLines
} from "../src/domain/line-breaker/lineRules.js";

describe("line breaker rules – extended", () => {
  describe("detectCompletedMonochromeLines", () => {
    it("returns empty array when no lines are completed", () => {
      const board = createEmptyBoard(4, 4);

      board.cells[0][0].color = "red";
      board.cells[0][1].color = "blue";
      board.occupiedCount = 2;

      const lines = detectCompletedMonochromeLines(board);

      expect(lines).toEqual([]);
    });

    it("returns empty array for a completely empty board", () => {
      const board = createEmptyBoard(5, 5);
      const lines = detectCompletedMonochromeLines(board);

      expect(lines).toEqual([]);
    });

    it("ignores a row with mixed colors", () => {
      const board = createEmptyBoard(3, 3);

      board.cells[0][0].color = "red";
      board.cells[0][1].color = "blue";
      board.cells[0][2].color = "red";
      board.occupiedCount = 3;

      const lines = detectCompletedMonochromeLines(board);

      expect(lines).toEqual([]);
    });

    it("ignores a row with a null gap", () => {
      const board = createEmptyBoard(3, 3);

      board.cells[1][0].color = "green";
      board.cells[1][2].color = "green";
      board.occupiedCount = 2;

      const lines = detectCompletedMonochromeLines(board);

      expect(lines).toEqual([]);
    });

    it("detects multiple horizontal lines simultaneously", () => {
      const board = createEmptyBoard(3, 3);

      for (let x = 0; x < 3; x += 1) {
        board.cells[0][x].color = "red";
        board.cells[2][x].color = "blue";
        board.occupiedCount += 2;
      }

      const lines = detectCompletedMonochromeLines(board);

      expect(lines).toHaveLength(2);
      expect(lines[0].orientation).toBe("row");
      expect(lines[0].color).toBe("red");
      expect(lines[1].orientation).toBe("row");
      expect(lines[1].color).toBe("blue");
    });

    it("detects both a horizontal and a vertical line at once", () => {
      const board = createEmptyBoard(3, 3);

      for (let x = 0; x < 3; x += 1) {
        board.cells[1][x].color = "yellow";
        board.occupiedCount += 1;
      }

      for (let y = 0; y < 3; y += 1) {
        if (board.cells[y][1].color === null) {
          board.cells[y][1].color = "yellow";
          board.occupiedCount += 1;
        }
      }

      const lines = detectCompletedMonochromeLines(board);
      const orientations = lines.map((l) => l.orientation).sort();

      expect(lines).toHaveLength(2);
      expect(orientations).toEqual(["column", "row"]);
    });

    it("works on a 1x1 board with a single color", () => {
      const board = createEmptyBoard(1, 1);

      board.cells[0][0].color = "red";
      board.occupiedCount = 1;

      const lines = detectCompletedMonochromeLines(board);

      expect(lines).toHaveLength(2);
      expect(lines[0].orientation).toBe("row");
      expect(lines[1].orientation).toBe("column");
    });
  });

  describe("clearCompletedLines", () => {
    it("returns a cloned board with zero cleared cells when no lines are given", () => {
      const board = createEmptyBoard(3, 3);

      board.cells[0][0].color = "red";
      board.occupiedCount = 1;

      const result = clearCompletedLines({ board, lines: [] });

      expect(result.clearedCellCount).toBe(0);
      expect(result.board.occupiedCount).toBe(1);
      expect(result.board.cells[0][0].color).toBe("red");
      expect(result.board).not.toBe(board);
    });

    it("resets occupiedCount correctly after clearing", () => {
      const board = createEmptyBoard(3, 3);

      for (let x = 0; x < 3; x += 1) {
        board.cells[0][x].color = "blue";
        board.occupiedCount += 1;
      }

      board.cells[1][0].color = "red";
      board.occupiedCount += 1;

      const lines = detectCompletedMonochromeLines(board);
      const result = clearCompletedLines({ board, lines });

      expect(result.clearedCellCount).toBe(3);
      expect(result.board.occupiedCount).toBe(1);
      expect(result.board.cells[1][0].color).toBe("red");
    });

    it("does not mutate the original board", () => {
      const board = createEmptyBoard(3, 1);

      for (let x = 0; x < 3; x += 1) {
        board.cells[0][x].color = "green";
        board.occupiedCount += 1;
      }

      const lines = detectCompletedMonochromeLines(board);
      clearCompletedLines({ board, lines });

      expect(board.occupiedCount).toBe(3);
      expect(board.cells[0][0].color).toBe("green");
    });
  });
});
