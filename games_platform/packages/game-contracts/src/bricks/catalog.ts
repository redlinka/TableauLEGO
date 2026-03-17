import { BrickRotationDefinition, BrickShapeDefinition } from "../domain/bricks.js";

type Cell = {
  x: number;
  y: number;
};

type ShapeSeed = {
  shapeId: string;
  label: string;
  category: BrickShapeDefinition["category"];
  weight: number;
  cells: Cell[];
};

const ROTATIONS: BrickRotationDefinition["rotation"][] = [0, 90, 180, 270];

function rotateCell(cell: Cell, rotation: BrickRotationDefinition["rotation"]): Cell {
  switch (rotation) {
    case 0:
      return { x: cell.x, y: cell.y };
    case 90:
      return { x: -cell.y, y: cell.x };
    case 180:
      return { x: -cell.x, y: -cell.y };
    case 270:
      return { x: cell.y, y: -cell.x };
  }
}

function normalizeCells(cells: Cell[]): Cell[] {
  const minX = Math.min(...cells.map((cell) => cell.x));
  const minY = Math.min(...cells.map((cell) => cell.y));

  return cells
    .map((cell) => ({
      x: cell.x - minX,
      y: cell.y - minY
    }))
    .sort((left, right) => {
      if (left.y === right.y) {
        return left.x - right.x;
      }

      return left.y - right.y;
    });
}

function cellsSignature(cells: Cell[]): string {
  return cells.map((cell) => `${cell.x}:${cell.y}`).join("|");
}

function buildRotations(baseCells: Cell[]): BrickRotationDefinition[] {
  const uniqueRotations = new Map<string, BrickRotationDefinition>();

  for (const rotation of ROTATIONS) {
    const rotated = normalizeCells(baseCells.map((cell) => rotateCell(cell, rotation)));
    const signature = cellsSignature(rotated);

    if (uniqueRotations.has(signature)) {
      continue;
    }

    uniqueRotations.set(signature, {
      rotation,
      width: Math.max(...rotated.map((cell) => cell.x)) + 1,
      height: Math.max(...rotated.map((cell) => cell.y)) + 1,
      cells: rotated
    });
  }

  return [...uniqueRotations.values()];
}

function buildShape(seed: ShapeSeed): BrickShapeDefinition {
  const baseCells = normalizeCells(seed.cells);
  const rotations = buildRotations(baseCells);
  const lacunary = seed.category === "lacunary";

  return {
    shapeId: seed.shapeId,
    label: seed.label,
    category: seed.category,
    weight: seed.weight,
    lacunary,
    baseCells,
    rotations
  };
}

const SHAPE_SEEDS: ShapeSeed[] = [
  {
    shapeId: "stud_1x1",
    label: "Stud 1x1",
    category: "rectangular",
    weight: 12,
    cells: [{ x: 0, y: 0 }]
  },
  {
    shapeId: "brick_1x2",
    label: "Brick 1x2",
    category: "rectangular",
    weight: 11,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 }
    ]
  },
  {
    shapeId: "brick_1x3",
    label: "Brick 1x3",
    category: "rectangular",
    weight: 10,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 2, y: 0 }
    ]
  },
  {
    shapeId: "brick_1x4",
    label: "Brick 1x4",
    category: "rectangular",
    weight: 9,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 2, y: 0 },
      { x: 3, y: 0 }
    ]
  },
  {
    shapeId: "brick_2x2",
    label: "Brick 2x2",
    category: "rectangular",
    weight: 8,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 0, y: 1 },
      { x: 1, y: 1 }
    ]
  },
  {
    shapeId: "brick_2x3",
    label: "Brick 2x3",
    category: "rectangular",
    weight: 7,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 2, y: 0 },
      { x: 0, y: 1 },
      { x: 1, y: 1 },
      { x: 2, y: 1 }
    ]
  },
  {
    shapeId: "l_corner_3",
    label: "L Corner 3",
    category: "polyomino",
    weight: 9,
    cells: [
      { x: 0, y: 0 },
      { x: 0, y: 1 },
      { x: 1, y: 1 }
    ]
  },
  {
    shapeId: "tee_4",
    label: "Tee 4",
    category: "polyomino",
    weight: 7,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 2, y: 0 },
      { x: 1, y: 1 }
    ]
  },
  {
    shapeId: "zigzag_4",
    label: "Zigzag 4",
    category: "polyomino",
    weight: 6,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 1, y: 1 },
      { x: 2, y: 1 }
    ]
  },
  {
    shapeId: "notch_2x3",
    label: "Notch 2x3",
    category: "lacunary",
    weight: 5,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 0, y: 1 },
      { x: 0, y: 2 },
      { x: 1, y: 2 }
    ]
  },
  {
    shapeId: "u_3x2",
    label: "U 3x2",
    category: "lacunary",
    weight: 4,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 2, y: 0 },
      { x: 0, y: 1 },
      { x: 2, y: 1 }
    ]
  },
  {
    shapeId: "frame_3x3",
    label: "Frame 3x3",
    category: "lacunary",
    weight: 2,
    cells: [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 2, y: 0 },
      { x: 0, y: 1 },
      { x: 2, y: 1 },
      { x: 0, y: 2 },
      { x: 1, y: 2 },
      { x: 2, y: 2 }
    ]
  }
];

export const INITIAL_BRICK_SHAPES: BrickShapeDefinition[] = SHAPE_SEEDS.map(buildShape);

