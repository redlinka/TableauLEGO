import { describe, expect, it } from "vitest";
import {
  calculateTurnTimeBonus,
  computeRemainingTurnMs,
  createTimedTurnState,
  resolveSessionSeed
} from "../src/domain/common/soloSessionSupport.js";

describe("solo session support utilities", () => {
  describe("resolveSessionSeed", () => {
    it("returns the provided seed when non-empty", () => {
      expect(resolveSessionSeed("my-seed")).toBe("my-seed");
    });

    it("trims whitespace from provided seed", () => {
      expect(resolveSessionSeed("  trimmed  ")).toBe("trimmed");
    });

    it("generates a UUID when seed is undefined", () => {
      const seed = resolveSessionSeed(undefined);

      expect(seed).toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/
      );
    });

    it("generates a UUID when seed is empty string", () => {
      const seed = resolveSessionSeed("");

      expect(seed).toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/
      );
    });

    it("generates a UUID when seed is only whitespace", () => {
      const seed = resolveSessionSeed("   ");

      expect(seed).toMatch(
        /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/
      );
    });
  });

  describe("createTimedTurnState", () => {
    it("creates a turn with the correct index and deadline", () => {
      const startMs = Date.parse("2025-01-01T00:00:00.000Z");
      const limitMs = 30000;
      const turn = createTimedTurnState(1, limitMs, startMs);

      expect(turn.index).toBe(1);
      expect(turn.limitMs).toBe(30000);
      expect(turn.startedAt).toBe("2025-01-01T00:00:00.000Z");
      expect(turn.deadlineAt).toBe("2025-01-01T00:00:30.000Z");
      expect(turn.skippedOffers).toBe(0);
      expect(turn.expiredTurns).toBe(0);
    });

    it("carries over skippedOffers and expiredTurns from input", () => {
      const turn = createTimedTurnState(5, 10000, Date.now(), {
        skippedOffers: 3,
        expiredTurns: 2
      });

      expect(turn.index).toBe(5);
      expect(turn.skippedOffers).toBe(3);
      expect(turn.expiredTurns).toBe(2);
    });
  });

  describe("computeRemainingTurnMs", () => {
    it("returns remaining ms when deadline is in the future", () => {
      const deadline = new Date(Date.now() + 15000).toISOString();
      const remaining = computeRemainingTurnMs(
        { deadlineAt: deadline },
        new Date()
      );

      expect(remaining).toBeGreaterThan(14000);
      expect(remaining).toBeLessThanOrEqual(15000);
    });

    it("returns zero when deadline is in the past", () => {
      const deadline = new Date(Date.now() - 5000).toISOString();
      const remaining = computeRemainingTurnMs(
        { deadlineAt: deadline },
        new Date()
      );

      expect(remaining).toBe(0);
    });

    it("returns zero when deadlineAt is undefined", () => {
      const remaining = computeRemainingTurnMs(
        { deadlineAt: undefined },
        new Date()
      );

      expect(remaining).toBe(0);
    });
  });

  describe("calculateTurnTimeBonus", () => {
    it("returns max bonus when plenty of time remains", () => {
      const deadline = new Date(Date.now() + 30000).toISOString();
      const bonus = calculateTurnTimeBonus({
        turn: { deadlineAt: deadline },
        turnTimeLimitMs: 30000,
        referenceDate: new Date(),
        maxBonus: 3
      });

      expect(bonus).toBe(3);
    });

    it("returns zero when the deadline has passed", () => {
      const deadline = new Date(Date.now() - 1000).toISOString();
      const bonus = calculateTurnTimeBonus({
        turn: { deadlineAt: deadline },
        turnTimeLimitMs: 30000,
        referenceDate: new Date(),
        maxBonus: 3
      });

      expect(bonus).toBe(0);
    });

    it("returns zero when deadlineAt is undefined", () => {
      const bonus = calculateTurnTimeBonus({
        turn: { deadlineAt: undefined },
        turnTimeLimitMs: 30000,
        referenceDate: new Date(),
        maxBonus: 3
      });

      expect(bonus).toBe(0);
    });

    it("returns zero when turnTimeLimitMs is zero", () => {
      const deadline = new Date(Date.now() + 10000).toISOString();
      const bonus = calculateTurnTimeBonus({
        turn: { deadlineAt: deadline },
        turnTimeLimitMs: 0,
        referenceDate: new Date(),
        maxBonus: 3
      });

      expect(bonus).toBe(0);
    });

    it("returns zero when maxBonus is zero", () => {
      const deadline = new Date(Date.now() + 10000).toISOString();
      const bonus = calculateTurnTimeBonus({
        turn: { deadlineAt: deadline },
        turnTimeLimitMs: 30000,
        referenceDate: new Date(),
        maxBonus: 0
      });

      expect(bonus).toBe(0);
    });

    it("returns proportional bonus at half time", () => {
      const now = Date.now();
      const deadline = new Date(now + 15000).toISOString();
      const bonus = calculateTurnTimeBonus({
        turn: { deadlineAt: deadline },
        turnTimeLimitMs: 30000,
        referenceDate: new Date(now),
        maxBonus: 2
      });

      expect(bonus).toBe(1);
    });
  });
});
