import { INITIAL_BRICK_SHAPES } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import {
  applyPlacement,
  createEmptyBoard,
  getAbsoluteCellsForPlacement,
  hasAnyValidPlacementForCatalog,
  hasAnyValidPlacementForShape,
  hydrateBoardState,
  listValidPlacementsForShape,
  serializeBoard,
  validatePlacement
} from "../src/domain/common/boardEngine.js";

function getShape(shapeId: string) {
  const shape = INITIAL_BRICK_SHAPES.find((entry) => entry.shapeId === shapeId);

  if (!shape) {
    throw new Error(`Missing shape '${shapeId}' in test catalog.`);
  }

  return shape;
}

describe("board engine – extended", () => {
  describe("createEmptyBoard", () => {
    it("creates a default 10x10 board with zero occupied cells", () => {
      const board = createEmptyBoard();

      expect(board.width).toBe(10);
      expect(board.height).toBe(10);
      expect(board.occupiedCount).toBe(0);
      expect(board.cells).toHaveLength(10);
      expect(board.cells[0]).toHaveLength(10);
      expect(board.cells[0][0].color).toBeNull();
    });

    it("creates a custom-sized board", () => {
      const board = createEmptyBoard(5, 3);

      expect(board.width).toBe(5);
      expect(board.height).toBe(3);
      expect(board.cells).toHaveLength(3);
      expect(board.cells[0]).toHaveLength(5);
    });

    it("creates a minimal 1x1 board", () => {
      const board = createEmptyBoard(1, 1);

      expect(board.width).toBe(1);
      expect(board.height).toBe(1);
      expect(board.cells).toHaveLength(1);
      expect(board.cells[0]).toHaveLength(1);
    });
  });

  describe("hydrateBoardState / serializeBoard roundtrip", () => {
    it("preserves board data through hydrate → serialize", () => {
      const board = createEmptyBoard(3, 3);
      const shape = getShape("stud_1x1");

      const placed = applyPlacement({
        board,
        shape,
        offer: {
          offerId: "offer_rt",
          shapeId: shape.shapeId,
          color: "red",
          issuedAtTurn: 1,
          sequenceIndex: 0
        },
        rotation: 0,
        anchorX: 1,
        anchorY: 1,
        turn: 1
      });

      const serialized = serializeBoard(placed);
      const hydrated = hydrateBoardState(serialized);

      expect(hydrated.width).toBe(placed.width);
      expect(hydrated.height).toBe(placed.height);
      expect(hydrated.occupiedCount).toBe(placed.occupiedCount);
      expect(hydrated.cells[1][1].color).toBe("red");
      expect(hydrated.cells[0][0].color).toBeNull();
    });

    it("preserves lastPlacement metadata", () => {
      const board = createEmptyBoard(3, 3);
      const shape = getShape("brick_1x2");

      const placed = applyPlacement({
        board,
        shape,
        offer: {
          offerId: "offer_lp",
          shapeId: shape.shapeId,
          color: "blue",
          issuedAtTurn: 1,
          sequenceIndex: 0
        },
        rotation: 0,
        anchorX: 0,
        anchorY: 0,
        turn: 1
      });

      const serialized = serializeBoard(placed);

      expect(serialized.lastPlacement).toBeDefined();
      expect(serialized.lastPlacement?.shapeId).toBe("brick_1x2");
      expect(serialized.lastPlacement?.color).toBe("blue");

      const hydrated = hydrateBoardState(serialized);

      expect(hydrated.lastPlacement).toEqual(serialized.lastPlacement);
    });
  });

  describe("validatePlacement – edge cases", () => {
    it("rejects placement with an unknown rotation angle", () => {
      const board = createEmptyBoard(5, 5);
      const shape = getShape("brick_1x2");

      const result = validatePlacement({
        board,
        shape,
        rotation: 45,
        anchorX: 0,
        anchorY: 0
      });

      expect(result.valid).toBe(false);
      expect(result.reason).toBe("unknown_rotation");
      expect(result.absoluteCells).toEqual([]);
    });

    it("rejects placement at negative x coordinate", () => {
      const board = createEmptyBoard(5, 5);
      const shape = getShape("stud_1x1");

      const result = validatePlacement({
        board,
        shape,
        rotation: 0,
        anchorX: -1,
        anchorY: 0
      });

      expect(result.valid).toBe(false);
      expect(result.reason).toBe("out_of_bounds");
    });

    it("rejects placement at negative y coordinate", () => {
      const board = createEmptyBoard(5, 5);
      const shape = getShape("stud_1x1");

      const result = validatePlacement({
        board,
        shape,
        rotation: 0,
        anchorX: 0,
        anchorY: -1
      });

      expect(result.valid).toBe(false);
      expect(result.reason).toBe("out_of_bounds");
    });

    it("rejects placement that overflows the right edge", () => {
      const board = createEmptyBoard(3, 3);
      const shape = getShape("brick_1x3");

      const result = validatePlacement({
        board,
        shape,
        rotation: 0,
        anchorX: 1,
        anchorY: 0
      });

      expect(result.valid).toBe(false);
      expect(result.reason).toBe("out_of_bounds");
    });

    it("rejects placement that overflows the bottom edge", () => {
      const board = createEmptyBoard(3, 3);
      const shape = getShape("brick_1x3");

      const result = validatePlacement({
        board,
        shape,
        rotation: 90,
        anchorX: 0,
        anchorY: 1
      });

      expect(result.valid).toBe(false);
      expect(result.reason).toBe("out_of_bounds");
    });

    it("accepts a valid placement on an empty board", () => {
      const board = createEmptyBoard(10, 10);
      const shape = getShape("brick_2x2");

      const result = validatePlacement({
        board,
        shape,
        rotation: 0,
        anchorX: 5,
        anchorY: 5
      });

      expect(result.valid).toBe(true);
      expect(result.absoluteCells).toHaveLength(4);
    });
  });

  describe("getAbsoluteCellsForPlacement", () => {
    it("returns empty array for unknown rotation", () => {
      const shape = getShape("brick_1x2");

      const cells = getAbsoluteCellsForPlacement({
        shape,
        rotation: 999,
        anchorX: 0,
        anchorY: 0
      });

      expect(cells).toEqual([]);
    });

    it("correctly offsets cells from anchor for a 2x2 shape", () => {
      const shape = getShape("brick_2x2");

      const cells = getAbsoluteCellsForPlacement({
        shape,
        rotation: 0,
        anchorX: 3,
        anchorY: 7
      });

      expect(cells).toEqual(
        expect.arrayContaining([
          { x: 3, y: 7 },
          { x: 4, y: 7 },
          { x: 3, y: 8 },
          { x: 4, y: 8 }
        ])
      );
      expect(cells).toHaveLength(4);
    });
  });

  describe("applyPlacement", () => {
    it("throws when applying an invalid placement", () => {
      const board = createEmptyBoard(3, 3);
      const shape = getShape("brick_1x2");

      expect(() =>
        applyPlacement({
          board,
          shape,
          offer: {
            offerId: "offer_throw",
            shapeId: shape.shapeId,
            color: "red",
            issuedAtTurn: 1,
            sequenceIndex: 0
          },
          rotation: 0,
          anchorX: 2,
          anchorY: 0,
          turn: 1
        })
      ).toThrow("Cannot apply invalid placement");
    });

    it("increments occupiedCount by the number of cells in the shape", () => {
      const board = createEmptyBoard(10, 10);
      const shape = getShape("l_corner_3");

      const placed = applyPlacement({
        board,
        shape,
        offer: {
          offerId: "offer_count",
          shapeId: shape.shapeId,
          color: "green",
          issuedAtTurn: 1,
          sequenceIndex: 0
        },
        rotation: 0,
        anchorX: 0,
        anchorY: 0,
        turn: 1
      });

      expect(placed.occupiedCount).toBe(3);
    });

    it("does not mutate the original board", () => {
      const board = createEmptyBoard(5, 5);
      const shape = getShape("stud_1x1");

      applyPlacement({
        board,
        shape,
        offer: {
          offerId: "offer_immutable",
          shapeId: shape.shapeId,
          color: "yellow",
          issuedAtTurn: 1,
          sequenceIndex: 0
        },
        rotation: 0,
        anchorX: 0,
        anchorY: 0,
        turn: 1
      });

      expect(board.occupiedCount).toBe(0);
      expect(board.cells[0][0].color).toBeNull();
    });

    it("sets cell metadata (shapeId, offerId, placedAtTurn)", () => {
      const board = createEmptyBoard(5, 5);
      const shape = getShape("stud_1x1");

      const placed = applyPlacement({
        board,
        shape,
        offer: {
          offerId: "offer_meta",
          shapeId: shape.shapeId,
          color: "orange",
          issuedAtTurn: 3,
          sequenceIndex: 2
        },
        rotation: 0,
        anchorX: 2,
        anchorY: 2,
        turn: 3
      });

      const cell = placed.cells[2][2];

      expect(cell.color).toBe("orange");
      expect(cell.shapeId).toBe("stud_1x1");
      expect(cell.offerId).toBe("offer_meta");
      expect(cell.placedAtTurn).toBe(3);
    });

    it("updates lastPlacement snapshot", () => {
      const board = createEmptyBoard(5, 5);
      const shape = getShape("brick_1x2");

      const placed = applyPlacement({
        board,
        shape,
        offer: {
          offerId: "offer_snap",
          shapeId: shape.shapeId,
          color: "blue",
          issuedAtTurn: 1,
          sequenceIndex: 0
        },
        rotation: 0,
        anchorX: 0,
        anchorY: 0,
        turn: 1
      });

      expect(placed.lastPlacement).toEqual({
        shapeId: "brick_1x2",
        color: "blue",
        anchorX: 0,
        anchorY: 0,
        rotation: 0,
        placedCells: [
          { x: 0, y: 0 },
          { x: 1, y: 0 }
        ],
        offerId: "offer_snap"
      });
    });
  });

  describe("hasAnyValidPlacementForShape", () => {
    it("returns true on an empty board for any shape", () => {
      const board = createEmptyBoard(10, 10);

      expect(hasAnyValidPlacementForShape(board, getShape("stud_1x1"))).toBe(true);
      expect(hasAnyValidPlacementForShape(board, getShape("brick_2x2"))).toBe(true);
    });

    it("returns false when the board is too small for the shape", () => {
      const board = createEmptyBoard(1, 1);

      expect(hasAnyValidPlacementForShape(board, getShape("brick_1x2"))).toBe(false);
    });
  });

  describe("hasAnyValidPlacementForCatalog", () => {
    it("returns true when at least one shape fits", () => {
      const board = createEmptyBoard(10, 10);

      expect(hasAnyValidPlacementForCatalog(board, INITIAL_BRICK_SHAPES)).toBe(true);
    });

    it("returns false on a completely full board", () => {
      const board = createEmptyBoard(2, 2);

      for (let y = 0; y < 2; y += 1) {
        for (let x = 0; x < 2; x += 1) {
          board.cells[y][x].color = "red";
          board.occupiedCount += 1;
        }
      }

      expect(hasAnyValidPlacementForCatalog(board, INITIAL_BRICK_SHAPES)).toBe(false);
    });
  });

  describe("listValidPlacementsForShape – edge cases", () => {
    it("returns zero placements on a full board", () => {
      const board = createEmptyBoard(3, 3);

      for (let y = 0; y < 3; y += 1) {
        for (let x = 0; x < 3; x += 1) {
          board.cells[y][x].color = "blue";
          board.occupiedCount += 1;
        }
      }

      const placements = listValidPlacementsForShape(board, getShape("stud_1x1"));

      expect(placements).toHaveLength(0);
    });

    it("includes all rotation variants in results", () => {
      const board = createEmptyBoard(10, 10);
      const shape = getShape("l_corner_3");
      const placements = listValidPlacementsForShape(board, shape);
      const rotations = [...new Set(placements.map((p) => p.rotation))];

      expect(rotations.length).toBe(shape.rotations.length);
    });
  });
});
