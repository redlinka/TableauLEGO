import { INITIAL_BRICK_SHAPES } from "@games-platform/game-contracts";
import { describe, expect, it } from "vitest";
import { DeterministicBrickSequence } from "../src/domain/common/deterministicSequence.js";

describe("deterministic brick sequence", () => {
  it("replays the exact same offers for the same seed", () => {
    const weightedColors = [
      { color: "red", weight: 4 },
      { color: "blue", weight: 2 },
      { color: "yellow", weight: 1 }
    ];
    const first = new DeterministicBrickSequence(
      "seed-a",
      INITIAL_BRICK_SHAPES,
      weightedColors
    );
    const second = new DeterministicBrickSequence(
      "seed-a",
      INITIAL_BRICK_SHAPES,
      weightedColors
    );

    const offersA = Array.from({ length: 8 }, (_value, index) => first.getOffer(index));
    const offersB = Array.from({ length: 8 }, (_value, index) => second.getOffer(index));

    expect(offersA).toEqual(offersB);
  });

  it("changes the sequence when the seed changes", () => {
    const weightedColors = [
      { color: "red", weight: 1 },
      { color: "green", weight: 1 }
    ];
    const first = new DeterministicBrickSequence(
      "seed-left",
      INITIAL_BRICK_SHAPES,
      weightedColors
    );
    const second = new DeterministicBrickSequence(
      "seed-right",
      INITIAL_BRICK_SHAPES,
      weightedColors
    );

    const firstOffer = first.getOffer(0);
    const secondOffer = second.getOffer(0);

    expect(firstOffer).not.toEqual(secondOffer);
  });
});
