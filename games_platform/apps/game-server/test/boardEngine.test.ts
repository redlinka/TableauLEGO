import { INITIAL_BRICK_SHAPES } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import {
  applyPlacement,
  createEmptyBoard,
  getAbsoluteCellsForPlacement,
  listValidPlacementsForShape,
  validatePlacement
} from "../src/domain/common/boardEngine.js";

function getShape(shapeId: string) {
  const shape = INITIAL_BRICK_SHAPES.find((entry) => entry.shapeId === shapeId);

  if (!shape) {
    throw new Error(`Missing shape '${shapeId}' in test catalog.`);
  }

  return shape;
}

describe("board engine", () => {
  it("keeps only unique rotations in the shared brick catalog", () => {
    expect(getShape("brick_2x2").rotations).toHaveLength(1);
    expect(getShape("l_corner_3").rotations).toHaveLength(4);
    expect(getShape("frame_3x3").rotations).toHaveLength(1);
  });

  it("computes absolute cells from a rotation and anchor", () => {
    const cells = getAbsoluteCellsForPlacement({
      shape: getShape("l_corner_3"),
      rotation: 90,
      anchorX: 4,
      anchorY: 2
    });

    expect(cells).toEqual([
      { x: 4, y: 2 },
      { x: 5, y: 2 },
      { x: 4, y: 3 }
    ]);
  });

  it("rejects out-of-bounds and colliding placements", () => {
    const shape = getShape("brick_1x2");
    const board = createEmptyBoard(3, 3);

    const outOfBounds = validatePlacement({
      board,
      shape,
      rotation: 0,
      anchorX: 2,
      anchorY: 0
    });

    expect(outOfBounds.valid).toBe(false);
    expect(outOfBounds.reason).toBe("out_of_bounds");

    const occupiedBoard = applyPlacement({
      board,
      shape,
      offer: {
        offerId: "offer_test",
        shapeId: shape.shapeId,
        color: "red",
        issuedAtTurn: 1,
        sequenceIndex: 0
      },
      rotation: 0,
      anchorX: 0,
      anchorY: 0,
      turn: 1
    });

    const collision = validatePlacement({
      board: occupiedBoard,
      shape,
      rotation: 90,
      anchorX: 0,
      anchorY: 0
    });

    expect(collision.valid).toBe(false);
    expect(collision.reason).toBe("collision");
  });

  it("lists every valid placement for a given shape", () => {
    const placements = listValidPlacementsForShape(
      createEmptyBoard(3, 3),
      getShape("brick_1x2")
    );

    expect(placements).toHaveLength(12);
    expect(placements[0]).toMatchObject({
      rotation: 0,
      anchorX: 0,
      anchorY: 0
    });
  });
});
