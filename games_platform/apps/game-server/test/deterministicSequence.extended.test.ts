import { INITIAL_BRICK_SHAPES } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import { DeterministicBrickSequence } from "../src/domain/common/deterministicSequence.js";

describe("deterministic brick sequence – extended", () => {
  const defaultColors = [
    { color: "red", weight: 4 },
    { color: "blue", weight: 2 },
    { color: "yellow", weight: 1 }
  ];

  it("generates unique offerIds for consecutive indices", () => {
    const sequence = new DeterministicBrickSequence(
      "unique-ids",
      INITIAL_BRICK_SHAPES,
      defaultColors
    );

    const ids = Array.from({ length: 20 }, (_value, index) =>
      sequence.getOffer(index).offerId
    );
    const unique = new Set(ids);

    expect(unique.size).toBe(20);
  });

  it("can access offers in any order (non-sequential)", () => {
    const sequence = new DeterministicBrickSequence(
      "random-access",
      INITIAL_BRICK_SHAPES,
      defaultColors
    );

    const offer5 = sequence.getOffer(5);
    const offer2 = sequence.getOffer(2);
    const offer5Again = sequence.getOffer(5);

    expect(offer5).toEqual(offer5Again);
    expect(offer2.offerId).not.toBe(offer5.offerId);
  });

  it("respects weight distribution over a large sample", () => {
    const weightedColors = [
      { color: "red", weight: 80 },
      { color: "blue", weight: 20 }
    ];
    const sequence = new DeterministicBrickSequence(
      "weight-check",
      INITIAL_BRICK_SHAPES,
      weightedColors
    );

    let redCount = 0;
    const sampleSize = 200;

    for (let index = 0; index < sampleSize; index += 1) {
      if (sequence.getOffer(index).color === "red") {
        redCount += 1;
      }
    }

    const redRatio = redCount / sampleSize;

    expect(redRatio).toBeGreaterThan(0.5);
    expect(redRatio).toBeLessThan(0.95);
  });

  it("always selects from the provided shape catalog", () => {
    const sequence = new DeterministicBrickSequence(
      "shape-validation",
      INITIAL_BRICK_SHAPES,
      defaultColors
    );
    const validShapeIds = new Set(INITIAL_BRICK_SHAPES.map((s) => s.shapeId));

    for (let index = 0; index < 50; index += 1) {
      const offer = sequence.getOffer(index);

      expect(validShapeIds.has(offer.shapeId)).toBe(true);
    }
  });

  it("always selects from the provided color list", () => {
    const sequence = new DeterministicBrickSequence(
      "color-validation",
      INITIAL_BRICK_SHAPES,
      defaultColors
    );
    const validColors = new Set(defaultColors.map((c) => c.color));

    for (let index = 0; index < 50; index += 1) {
      const offer = sequence.getOffer(index);

      expect(validColors.has(offer.color)).toBe(true);
    }
  });

  it("sets issuedAtTurn = sequenceIndex + 1", () => {
    const sequence = new DeterministicBrickSequence(
      "turn-numbering",
      INITIAL_BRICK_SHAPES,
      defaultColors
    );

    for (let index = 0; index < 5; index += 1) {
      const offer = sequence.getOffer(index);

      expect(offer.sequenceIndex).toBe(index);
      expect(offer.issuedAtTurn).toBe(index + 1);
    }
  });
});
