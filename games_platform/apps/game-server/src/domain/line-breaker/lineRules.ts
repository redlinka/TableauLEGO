import { CellOffset, ClearedLineSummary } from "@games-platform/game-contracts";
import { EngineBoard } from "../common/boardEngine.js";

export interface DetectedCompletedLine extends ClearedLineSummary {
  cells: CellOffset[];
}

function cloneBoard(board: EngineBoard): EngineBoard {
  return {
    width: board.width,
    height: board.height,
    occupiedCount: board.occupiedCount,
    lastPlacement: board.lastPlacement,
    cells: board.cells.map((row) => row.map((cell) => ({ ...cell })))
  };
}

function detectMonochromeColor(colors: Array<string | null>): string | null {
  const first = colors[0];

  if (!first) {
    return null;
  }

  return colors.every((color) => color === first) ? first : null;
}

export function detectCompletedMonochromeLines(board: EngineBoard): DetectedCompletedLine[] {
  const lines: DetectedCompletedLine[] = [];

  for (let y = 0; y < board.height; y += 1) {
    const rowColors = board.cells[y].map((cell) => cell.color);
    const color = detectMonochromeColor(rowColors);

    if (!color) {
      continue;
    }

    lines.push({
      orientation: "row",
      index: y,
      color,
      cellCount: board.width,
      cells: Array.from({ length: board.width }, (_value, x) => ({ x, y }))
    });
  }

  for (let x = 0; x < board.width; x += 1) {
    const columnColors = Array.from({ length: board.height }, (_value, y) => board.cells[y][x].color);
    const color = detectMonochromeColor(columnColors);

    if (!color) {
      continue;
    }

    lines.push({
      orientation: "column",
      index: x,
      color,
      cellCount: board.height,
      cells: Array.from({ length: board.height }, (_value, y) => ({ x, y }))
    });
  }

  return lines;
}

export function clearCompletedLines(input: {
  board: EngineBoard;
  lines: DetectedCompletedLine[];
}): {
  board: EngineBoard;
  clearedCellCount: number;
} {
  if (input.lines.length === 0) {
    return {
      board: cloneBoard(input.board),
      clearedCellCount: 0
    };
  }

  const nextBoard = cloneBoard(input.board);
  const clearedKeys = new Set<string>();

  for (const line of input.lines) {
    for (const cell of line.cells) {
      clearedKeys.add(`${cell.x}:${cell.y}`);
    }
  }

  for (const key of clearedKeys) {
    const [x, y] = key.split(":").map((value) => Number.parseInt(value, 10));

    if (nextBoard.cells[y][x].color !== null) {
      nextBoard.cells[y][x] = {
        color: null
      };
      nextBoard.occupiedCount -= 1;
    }
  }

  return {
    board: nextBoard,
    clearedCellCount: clearedKeys.size
  };
}
