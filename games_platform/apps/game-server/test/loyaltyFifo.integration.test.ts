import { LoyaltyEntryType } from "@games-platform/game-contracts";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";

describe("loyalty FIFO consumption by expiration", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let playerId = "";

  beforeAll(async () => {
    harness = await createDuplicateHarness();
    const auth = await harness.createGuest("FifoTester");
    playerId = auth.player.playerId;
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("stores remainingPoints when appending a positive ledger entry", async () => {
    await harness.repositories.loyaltyLedger.append({
      playerId,
      entryType: LoyaltyEntryType.SessionReward,
      pointsDelta: 50,
      balanceAfter: 50,
      reason: "test_reward_1",
      expiresAt: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString()
    });

    const entries = await harness.repositories.loyaltyLedger.listByPlayerId(playerId, 1);

    expect(entries).toHaveLength(1);
    expect(entries[0].pointsDelta).toBe(50);
    expect(entries[0].remainingPoints).toBe(50);
    expect(entries[0].expiresAt).toBeDefined();
  });

  it("getAvailableBalance returns sum of non-expired remainingPoints", async () => {
    const balance = await harness.repositories.loyaltyLedger.getAvailableBalance(playerId);

    expect(balance).toBe(50);
  });

  it("consumes points FIFO by expiration date (soonest first)", async () => {
    await harness.repositories.loyaltyLedger.append({
      playerId,
      entryType: LoyaltyEntryType.SessionReward,
      pointsDelta: 30,
      balanceAfter: 80,
      reason: "test_reward_expires_soon",
      expiresAt: new Date(Date.now() + 1 * 24 * 60 * 60 * 1000).toISOString()
    });

    await harness.repositories.loyaltyLedger.append({
      playerId,
      entryType: LoyaltyEntryType.SessionReward,
      pointsDelta: 40,
      balanceAfter: 120,
      reason: "test_reward_expires_later",
      expiresAt: new Date(Date.now() + 60 * 24 * 60 * 60 * 1000).toISOString()
    });

    const consumed = await harness.repositories.loyaltyLedger.consumePointsFifo(playerId, 50);

    expect(consumed).toBe(50);

    const balance = await harness.repositories.loyaltyLedger.getAvailableBalance(playerId);

    expect(balance).toBe(70);
  });

  it("does not consume expired points", async () => {
    const expiredPlayerId = (await harness.createGuest("ExpiredTester")).player.playerId;

    await harness.repositories.loyaltyLedger.append({
      playerId: expiredPlayerId,
      entryType: LoyaltyEntryType.SessionReward,
      pointsDelta: 100,
      balanceAfter: 100,
      reason: "test_expired",
      expiresAt: new Date(Date.now() - 1000).toISOString()
    });

    const balance = await harness.repositories.loyaltyLedger.getAvailableBalance(expiredPlayerId);

    expect(balance).toBe(0);

    const consumed = await harness.repositories.loyaltyLedger.consumePointsFifo(expiredPlayerId, 50);

    expect(consumed).toBe(0);
  });

  it("partially consumes an entry when not enough points needed", async () => {
    const partialPlayerId = (await harness.createGuest("PartialTester")).player.playerId;

    await harness.repositories.loyaltyLedger.append({
      playerId: partialPlayerId,
      entryType: LoyaltyEntryType.SessionReward,
      pointsDelta: 100,
      balanceAfter: 100,
      reason: "test_partial",
      expiresAt: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString()
    });

    const consumed = await harness.repositories.loyaltyLedger.consumePointsFifo(partialPlayerId, 30);

    expect(consumed).toBe(30);

    const balance = await harness.repositories.loyaltyLedger.getAvailableBalance(partialPlayerId);

    expect(balance).toBe(70);
  });

  it("handles entries without expiresAt (never expire)", async () => {
    const noExpiryPlayerId = (await harness.createGuest("NoExpiryTester")).player.playerId;

    await harness.repositories.loyaltyLedger.append({
      playerId: noExpiryPlayerId,
      entryType: LoyaltyEntryType.SessionReward,
      pointsDelta: 50,
      balanceAfter: 50,
      reason: "test_no_expiry"
    });

    const balance = await harness.repositories.loyaltyLedger.getAvailableBalance(noExpiryPlayerId);

    expect(balance).toBe(50);

    const consumed = await harness.repositories.loyaltyLedger.consumePointsFifo(noExpiryPlayerId, 25);

    expect(consumed).toBe(25);
  });

  it("returns 0 consumed when player has no entries", async () => {
    const emptyPlayerId = (await harness.createGuest("EmptyTester")).player.playerId;

    const consumed = await harness.repositories.loyaltyLedger.consumePointsFifo(emptyPlayerId, 100);

    expect(consumed).toBe(0);
  });

  it("getAvailableBalance returns 0 for player with no entries", async () => {
    const emptyPlayerId = (await harness.createGuest("EmptyBalance")).player.playerId;

    const balance = await harness.repositories.loyaltyLedger.getAvailableBalance(emptyPlayerId);

    expect(balance).toBe(0);
  });
});
