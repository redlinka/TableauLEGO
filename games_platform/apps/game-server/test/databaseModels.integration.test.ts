import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";
import { GameMode, GameType, LoyaltyEntryType } from "@games-platform/game-contracts";

describe("MongoDB models and repositories", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;

  beforeAll(async () => {
    harness = await createDuplicateHarness();
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  describe("PlayerModel", () => {
    it("creates a guest player with default loyalty summary", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("DBTester");

      expect(identity.playerId).toMatch(/^ply_/);
      expect(identity.authSource).toBe("guest");
      expect(identity.publicAlias).toBe("DBTester");
    });

    it("retrieves a player profile with stats and loyalty summary", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("ProfileTester");
      const profile = await harness.repositories.players.getProfile(identity.playerId);

      expect(profile).not.toBeNull();
      expect(profile!.identity.playerId).toBe(identity.playerId);
      expect(profile!.statsSummary.totalSessions).toBe(0);
      expect(profile!.statsSummary.totalScore).toBe(0);
      expect(profile!.loyaltySummary.balance).toBe(0);
      expect(profile!.loyaltySummary.lifetimeEarned).toBe(0);
    });

    it("applies loyalty delta correctly", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("DeltaTester");

      const summary = await harness.repositories.players.applyLoyaltyDelta({
        playerId: identity.playerId,
        pointsDelta: 100
      });

      expect(summary.balance).toBe(100);
      expect(summary.lifetimeEarned).toBe(100);
    });

    it("records completed session stats", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("StatsTester");

      await harness.repositories.players.recordCompletedSession({
        playerId: identity.playerId,
        score: 500,
        endedAt: new Date(),
        outcome: "solo"
      });

      const profile = await harness.repositories.players.getProfile(identity.playerId);

      expect(profile!.statsSummary.totalSessions).toBe(1);
      expect(profile!.statsSummary.totalScore).toBe(500);
    });

    it("returns null for unknown player", async () => {
      const profile = await harness.repositories.players.getProfile("ply_nonexistent");

      expect(profile).toBeNull();
    });

    it("sanitizes alias longer than 24 characters", async () => {
      const identity = await harness.repositories.players.createGuestPlayer(
        "ThisIsAVeryLongAliasNameThatExceedsTwentyFourCharacters"
      );

      expect(identity.publicAlias.length).toBeLessThanOrEqual(24);
    });

    it("generates alias for empty input", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("");

      expect(identity.publicAlias).toMatch(/^Guest-/);
    });
  });

  describe("LoyaltyLedgerModel", () => {
    it("appends a ledger entry with expiresAt", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("LedgerTester");
      const expiresAt = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString();

      await harness.repositories.loyaltyLedger.append({
        playerId: identity.playerId,
        entryType: LoyaltyEntryType.SessionReward,
        pointsDelta: 25,
        balanceAfter: 25,
        expiresAt,
        reason: "test_game_reward",
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 150
      });

      const entries = await harness.repositories.loyaltyLedger.listByPlayerId(identity.playerId);

      expect(entries).toHaveLength(1);
      expect(entries[0].pointsDelta).toBe(25);
      expect(entries[0].expiresAt).toBeDefined();
      expect(entries[0].remainingPoints).toBe(25);
      expect(entries[0].gameType).toBe(GameType.ImageRebuild);
      expect(entries[0].mode).toBe(GameMode.Solo);
    });

    it("appends a redemption entry (negative delta, no expiresAt)", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("RedeemLedger");

      await harness.repositories.loyaltyLedger.append({
        playerId: identity.playerId,
        entryType: LoyaltyEntryType.Redemption,
        pointsDelta: -200,
        balanceAfter: 0,
        reason: "redemption_5pct_discount"
      });

      const entries = await harness.repositories.loyaltyLedger.listByPlayerId(identity.playerId);

      expect(entries).toHaveLength(1);
      expect(entries[0].pointsDelta).toBe(-200);
      expect(entries[0].expiresAt).toBeUndefined();
      expect(entries[0].remainingPoints).toBeUndefined();
    });

    it("counts entries by player", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("CountTester");

      await harness.repositories.loyaltyLedger.append({
        playerId: identity.playerId,
        entryType: LoyaltyEntryType.SessionReward,
        pointsDelta: 10,
        reason: "count_test_1"
      });
      await harness.repositories.loyaltyLedger.append({
        playerId: identity.playerId,
        entryType: LoyaltyEntryType.SessionReward,
        pointsDelta: 20,
        reason: "count_test_2"
      });

      const count = await harness.repositories.loyaltyLedger.countByPlayerId(identity.playerId);

      expect(count).toBe(2);
    });

    it("lists entries sorted by createdAt descending", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("SortTester");

      await harness.repositories.loyaltyLedger.append({
        playerId: identity.playerId,
        entryType: LoyaltyEntryType.SessionReward,
        pointsDelta: 10,
        reason: "first_entry"
      });
      await harness.repositories.loyaltyLedger.append({
        playerId: identity.playerId,
        entryType: LoyaltyEntryType.SessionReward,
        pointsDelta: 20,
        reason: "second_entry"
      });

      const entries = await harness.repositories.loyaltyLedger.listByPlayerId(identity.playerId);

      expect(entries[0].reason).toBe("second_entry");
      expect(entries[1].reason).toBe("first_entry");
    });
  });

  describe("HistoryModel", () => {
    it("creates a history entry with full metadata", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("HistoryTester");
      const now = new Date();

      const inserted = await harness.repositories.history.create({
        historyId: `hist_test_${Date.now()}`,
        playerId: identity.playerId,
        sessionId: `ses_test_${Date.now()}`,
        gameType: GameType.LineBreaker,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 350,
        rewardPoints: 22,
        startedAt: new Date(now.getTime() - 60000),
        endedAt: now,
        metadata: {
          finishReason: "no_more_moves",
          formulaVersion: "line_breaker_v1"
        }
      });

      expect(inserted).toBe(true);
    });

    it("prevents duplicate historyId insertion", async () => {
      const identity = await harness.repositories.players.createGuestPlayer("DupHistTester");
      const historyId = `hist_dup_${Date.now()}`;
      const now = new Date();

      await harness.repositories.history.create({
        historyId,
        playerId: identity.playerId,
        sessionId: `ses_dup1_${Date.now()}`,
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 100,
        startedAt: now,
        endedAt: now
      });

      const secondInsert = await harness.repositories.history.create({
        historyId,
        playerId: identity.playerId,
        sessionId: `ses_dup2_${Date.now()}`,
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        outcome: "solo",
        score: 200,
        startedAt: now,
        endedAt: now
      });

      expect(secondInsert).toBe(false);
    });
  });
});
