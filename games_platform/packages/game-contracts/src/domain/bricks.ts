export type BrickCategory = "rectangular" | "polyomino" | "lacunary";

export interface BrickRotationDefinition {
  rotation: 0 | 90 | 180 | 270;
  width: number;
  height: number;
  cells: {
    x: number;
    y: number;
  }[];
}

export interface BrickShapeDefinition {
  shapeId: string;
  label: string;
  category: BrickCategory;
  weight: number;
  lacunary: boolean;
  baseCells: {
    x: number;
    y: number;
  }[];
  rotations: BrickRotationDefinition[];
}

