import { BrickOffer, BrickShapeDefinition } from "@games-platform/game-contracts";

export interface WeightedColorOption {
  color: string;
  weight: number;
}

function hashStringToUint32(input: string): number {
  let hash = 2166136261;

  for (let index = 0; index < input.length; index += 1) {
    hash ^= input.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }

  return hash >>> 0;
}

function createPseudoRandom(seed: number): () => number {
  let state = seed >>> 0;

  return () => {
    state = (state + 0x6d2b79f5) >>> 0;
    let current = Math.imul(state ^ (state >>> 15), 1 | state);
    current ^= current + Math.imul(current ^ (current >>> 7), 61 | current);
    return ((current ^ (current >>> 14)) >>> 0) / 4294967296;
  };
}

function pickWeighted<T>(items: Array<{ item: T; weight: number }>, randomValue: number): T {
  const totalWeight = items.reduce((sum, entry) => sum + entry.weight, 0);
  let cursor = randomValue * totalWeight;

  for (const entry of items) {
    cursor -= entry.weight;

    if (cursor <= 0) {
      return entry.item;
    }
  }

  return items[items.length - 1].item;
}

export class DeterministicBrickSequence {
  private readonly shapeWeights: Array<{ item: BrickShapeDefinition; weight: number }>;
  private readonly colorWeights: Array<{ item: string; weight: number }>;

  constructor(
    private readonly seed: string,
    shapes: BrickShapeDefinition[],
    weightedColors: WeightedColorOption[]
  ) {
    this.shapeWeights = shapes.map((shape) => ({
      item: shape,
      weight: Math.max(1, shape.weight)
    }));
    this.colorWeights = weightedColors.map((entry) => ({
      item: entry.color,
      weight: Math.max(1, entry.weight)
    }));
  }

  getOffer(sequenceIndex: number): BrickOffer {
    const shapeRandom = createPseudoRandom(
      hashStringToUint32(`${this.seed}:shape:${sequenceIndex}`)
    )();
    const colorRandom = createPseudoRandom(
      hashStringToUint32(`${this.seed}:color:${sequenceIndex}`)
    )();
    const shape = pickWeighted(this.shapeWeights, shapeRandom);
    const color = pickWeighted(this.colorWeights, colorRandom);

    return {
      offerId: `offer_${sequenceIndex}_${hashStringToUint32(`${this.seed}:offer:${sequenceIndex}`).toString(16)}`,
      shapeId: shape.shapeId,
      color,
      issuedAtTurn: sequenceIndex + 1,
      sequenceIndex
    };
  }
}
