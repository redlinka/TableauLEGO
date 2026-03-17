import {
  BoardState,
  BrickOffer,
  BrickShapeDefinition,
  CellOffset,
  PlacementSnapshot
} from "@games-platform/game-contracts";

export interface EngineBoardCell {
  color: string | null;
  shapeId?: string;
  offerId?: string;
  placedAtTurn?: number;
}

export interface EngineBoard {
  width: number;
  height: number;
  cells: EngineBoardCell[][];
  occupiedCount: number;
  lastPlacement?: PlacementSnapshot;
}

export interface PlacementValidationResult {
  valid: boolean;
  reason?: "unknown_rotation" | "out_of_bounds" | "collision";
  absoluteCells: CellOffset[];
}

export interface ValidPlacementCandidate {
  rotation: number;
  anchorX: number;
  anchorY: number;
  absoluteCells: CellOffset[];
}

function createEmptyCell(): EngineBoardCell {
  return {
    color: null
  };
}

export function createEmptyBoard(width = 10, height = 10): EngineBoard {
  return {
    width,
    height,
    cells: Array.from({ length: height }, () =>
      Array.from({ length: width }, () => createEmptyCell())
    ),
    occupiedCount: 0
  };
}

export function hydrateBoardState(boardState: BoardState): EngineBoard {
  return {
    width: boardState.width,
    height: boardState.height,
    occupiedCount: boardState.occupiedCount,
    lastPlacement: boardState.lastPlacement,
    cells: boardState.cells.map((row) =>
      row.map((color) => ({
        color
      }))
    )
  };
}

export function serializeBoard(board: EngineBoard): BoardState {
  return {
    width: board.width,
    height: board.height,
    occupiedCount: board.occupiedCount,
    lastPlacement: board.lastPlacement,
    cells: board.cells.map((row) => row.map((cell) => cell.color))
  };
}

function getRotationDefinition(
  shape: BrickShapeDefinition,
  rotation: number
): BrickShapeDefinition["rotations"][number] | undefined {
  return shape.rotations.find((candidate) => candidate.rotation === rotation);
}

export function getAbsoluteCellsForPlacement(input: {
  shape: BrickShapeDefinition;
  rotation: number;
  anchorX: number;
  anchorY: number;
}): CellOffset[] {
  const rotationDefinition = getRotationDefinition(input.shape, input.rotation);

  if (!rotationDefinition) {
    return [];
  }

  return rotationDefinition.cells.map((cell) => ({
    x: input.anchorX + cell.x,
    y: input.anchorY + cell.y
  }));
}

export function validatePlacement(input: {
  board: EngineBoard;
  shape: BrickShapeDefinition;
  rotation: number;
  anchorX: number;
  anchorY: number;
}): PlacementValidationResult {
  const rotationDefinition = getRotationDefinition(input.shape, input.rotation);

  if (!rotationDefinition) {
    return {
      valid: false,
      reason: "unknown_rotation",
      absoluteCells: []
    };
  }

  const absoluteCells = getAbsoluteCellsForPlacement(input);

  for (const cell of absoluteCells) {
    if (
      cell.x < 0 ||
      cell.y < 0 ||
      cell.x >= input.board.width ||
      cell.y >= input.board.height
    ) {
      return {
        valid: false,
        reason: "out_of_bounds",
        absoluteCells
      };
    }

    if (input.board.cells[cell.y][cell.x].color !== null) {
      return {
        valid: false,
        reason: "collision",
        absoluteCells
      };
    }
  }

  return {
    valid: true,
    absoluteCells
  };
}

export function listValidPlacementsForShape(
  board: EngineBoard,
  shape: BrickShapeDefinition
): ValidPlacementCandidate[] {
  const placements: ValidPlacementCandidate[] = [];

  for (const rotationDefinition of shape.rotations) {
    for (let anchorY = 0; anchorY <= board.height - rotationDefinition.height; anchorY += 1) {
      for (let anchorX = 0; anchorX <= board.width - rotationDefinition.width; anchorX += 1) {
        const validation = validatePlacement({
          board,
          shape,
          rotation: rotationDefinition.rotation,
          anchorX,
          anchorY
        });

        if (!validation.valid) {
          continue;
        }

        placements.push({
          rotation: rotationDefinition.rotation,
          anchorX,
          anchorY,
          absoluteCells: validation.absoluteCells
        });
      }
    }
  }

  return placements;
}

export function hasAnyValidPlacementForShape(
  board: EngineBoard,
  shape: BrickShapeDefinition
): boolean {
  return listValidPlacementsForShape(board, shape).length > 0;
}

export function hasAnyValidPlacementForCatalog(
  board: EngineBoard,
  shapes: BrickShapeDefinition[]
): boolean {
  return shapes.some((shape) => hasAnyValidPlacementForShape(board, shape));
}

export function applyPlacement(input: {
  board: EngineBoard;
  shape: BrickShapeDefinition;
  offer: BrickOffer;
  rotation: number;
  anchorX: number;
  anchorY: number;
  turn: number;
}): EngineBoard {
  const validation = validatePlacement({
    board: input.board,
    shape: input.shape,
    rotation: input.rotation,
    anchorX: input.anchorX,
    anchorY: input.anchorY
  });

  if (!validation.valid) {
    throw new Error(`Cannot apply invalid placement: ${validation.reason}`);
  }

  const nextBoard: EngineBoard = {
    width: input.board.width,
    height: input.board.height,
    occupiedCount: input.board.occupiedCount,
    lastPlacement: input.board.lastPlacement,
    cells: input.board.cells.map((row) => row.map((cell) => ({ ...cell })))
  };

  for (const cell of validation.absoluteCells) {
    nextBoard.cells[cell.y][cell.x] = {
      color: input.offer.color,
      shapeId: input.shape.shapeId,
      offerId: input.offer.offerId,
      placedAtTurn: input.turn
    };
    nextBoard.occupiedCount += 1;
  }

  nextBoard.lastPlacement = {
    shapeId: input.shape.shapeId,
    color: input.offer.color,
    anchorX: input.anchorX,
    anchorY: input.anchorY,
    rotation: input.rotation,
    placedCells: validation.absoluteCells,
    offerId: input.offer.offerId
  };

  return nextBoard;
}

