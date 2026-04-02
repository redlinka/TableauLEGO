import { describe, expect, it } from "vitest";
import {
  AuthSource,
  GameMode,
  GameType,
  LoyaltyEntryType,
  SessionStatus,
  ConnectionState,
  PlayerSeatType,
  EventType,
  INITIAL_BRICK_SHAPES
} from "@games-platform/game-contracts";

describe("game-contracts enums and constants", () => {
  describe("GameType enum", () => {
    it("contains ImageRebuild and LineBreaker", () => {
      expect(GameType.ImageRebuild).toBeDefined();
      expect(GameType.LineBreaker).toBeDefined();
    });

    it("has exactly 2 game types", () => {
      expect(Object.values(GameType)).toHaveLength(2);
    });
  });

  describe("GameMode enum", () => {
    it("contains Solo and Duplicate2P", () => {
      expect(GameMode.Solo).toBeDefined();
      expect(GameMode.Duplicate2P).toBeDefined();
    });

    it("has exactly 2 game modes", () => {
      expect(Object.values(GameMode)).toHaveLength(2);
    });
  });

  describe("AuthSource enum", () => {
    it("contains Guest and Php sources", () => {
      expect(AuthSource.Guest).toBeDefined();
      expect(AuthSource.Php).toBeDefined();
    });
  });

  describe("SessionStatus enum", () => {
    it("contains all required statuses", () => {
      expect(SessionStatus.Waiting).toBeDefined();
      expect(SessionStatus.Active).toBeDefined();
      expect(SessionStatus.Completed).toBeDefined();
      expect(SessionStatus.Abandoned).toBeDefined();
    });
  });

  describe("LoyaltyEntryType enum", () => {
    it("contains all loyalty entry types", () => {
      expect(LoyaltyEntryType.SessionReward).toBeDefined();
      expect(LoyaltyEntryType.DuplicateBonus).toBeDefined();
      expect(LoyaltyEntryType.AdminAdjustment).toBeDefined();
      expect(LoyaltyEntryType.Redemption).toBeDefined();
    });
  });

  describe("ConnectionState enum", () => {
    it("contains connection states", () => {
      expect(ConnectionState.Connected).toBeDefined();
      expect(ConnectionState.Disconnected).toBeDefined();
      expect(ConnectionState.Reconnecting).toBeDefined();
    });
  });

  describe("PlayerSeatType enum", () => {
    it("contains seat types", () => {
      expect(PlayerSeatType.Solo).toBeDefined();
      expect(PlayerSeatType.Host).toBeDefined();
      expect(PlayerSeatType.Guest).toBeDefined();
    });
  });

  describe("EventType enum", () => {
    it("has at least 10 event types", () => {
      expect(Object.values(EventType).length).toBeGreaterThanOrEqual(10);
    });
  });

  describe("INITIAL_BRICK_SHAPES catalog", () => {
    it("contains at least 6 shapes", () => {
      expect(INITIAL_BRICK_SHAPES.length).toBeGreaterThanOrEqual(6);
    });

    it("each shape has required properties", () => {
      for (const shape of INITIAL_BRICK_SHAPES) {
        expect(shape.shapeId).toBeDefined();
        expect(typeof shape.shapeId).toBe("string");
        expect(shape.rotations).toBeDefined();
        expect(Array.isArray(shape.rotations)).toBe(true);
        expect(shape.rotations.length).toBeGreaterThan(0);
      }
    });

    it("each rotation has cells with x,y coordinates", () => {
      for (const shape of INITIAL_BRICK_SHAPES) {
        for (const rotation of shape.rotations) {
          expect(rotation.rotation).toBeDefined();
          expect([0, 90, 180, 270]).toContain(rotation.rotation);
          expect(rotation.cells).toBeDefined();
          expect(Array.isArray(rotation.cells)).toBe(true);

          for (const cell of rotation.cells) {
            expect(typeof cell.x).toBe("number");
            expect(typeof cell.y).toBe("number");
            expect(cell.x).toBeGreaterThanOrEqual(0);
            expect(cell.y).toBeGreaterThanOrEqual(0);
          }
        }
      }
    });

    it("contains lacunary (non-rectangular) shapes", () => {
      const lacunaryShapes = INITIAL_BRICK_SHAPES.filter((shape) => {
        const rotation = shape.rotations[0];
        const cellCount = rotation.cells.length;
        const boundingArea = rotation.width * rotation.height;
        return cellCount < boundingArea;
      });

      expect(lacunaryShapes.length).toBeGreaterThanOrEqual(1);
    });

    it("each shape has a unique shapeId", () => {
      const ids = INITIAL_BRICK_SHAPES.map((shape) => shape.shapeId);
      const uniqueIds = new Set(ids);

      expect(uniqueIds.size).toBe(ids.length);
    });
  });
});
