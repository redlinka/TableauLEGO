import {
  LoyaltyBalanceResponse,
  LoyaltyRedeemResponse
} from "@games-platform/game-contracts";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";

describe("loyalty redeem endpoint", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let accessToken = "";
  let playerId = "";

  beforeAll(async () => {
    harness = await createDuplicateHarness();
    const auth = await harness.createGuest("RedeemTester");
    accessToken = auth.accessToken;
    playerId = auth.player.playerId;

    await harness.repositories.players.applyLoyaltyDelta({
      playerId,
      pointsDelta: 650
    });
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("redeems a valid tier and deducts the exact points", async () => {
    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/loyalty/redeem",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { points: 200 }
    });
    const body = response.json() as LoyaltyRedeemResponse;

    expect(response.statusCode).toBe(200);
    expect(body.success).toBe(true);
    expect(body.pointsDeducted).toBe(200);
    expect(body.discountPercent).toBe(5);
    expect(body.balanceAfter).toBe(450);
  });

  it("confirms the balance is updated after redemption", async () => {
    const response = await harness.app.inject({
      method: "GET",
      url: "/api/v1/loyalty/balance",
      headers: { authorization: `Bearer ${accessToken}` }
    });
    const body = response.json() as LoyaltyBalanceResponse;

    expect(response.statusCode).toBe(200);
    expect(body.summary.balance).toBe(450);

    const redeemEntry = body.recentEntries.find(
      (e) => e.entryType === "redemption"
    );

    expect(redeemEntry).toBeDefined();
    expect(redeemEntry?.pointsDelta).toBe(-200);
    expect(redeemEntry?.reason).toBe("redemption_5pct_discount");
  });

  it("rejects redemption when balance is insufficient", async () => {
    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/loyalty/redeem",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { points: 1000 }
    });

    expect(response.statusCode).toBe(409);
    expect(response.json().error.code).toBe("INSUFFICIENT_BALANCE");
  });

  it("rejects an invalid tier value", async () => {
    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/loyalty/redeem",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { points: 300 }
    });

    expect(response.statusCode).toBe(400);
    expect(response.json().error.code).toBe("INVALID_TIER");
  });

  it("rejects redemption without authentication", async () => {
    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/loyalty/redeem",
      payload: { points: 200 }
    });

    expect(response.statusCode).toBe(401);
  });

  it("records the orderRef in ledger metadata", async () => {
    await harness.repositories.players.applyLoyaltyDelta({
      playerId,
      pointsDelta: 200
    });

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/loyalty/redeem",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { points: 200, orderRef: "ORDER-42" }
    });

    expect(response.statusCode).toBe(200);

    const ledgerResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/loyalty/ledger?limit=1",
      headers: { authorization: `Bearer ${accessToken}` }
    });
    const ledger = ledgerResponse.json();
    const latest = ledger.items[0];

    expect(latest.entryType).toBe("redemption");
    expect(latest.metadata?.orderRef).toBe("ORDER-42");
  });

  it("allows the second-tier redemption when balance is sufficient", async () => {
    await harness.repositories.players.applyLoyaltyDelta({
      playerId,
      pointsDelta: 500
    });

    const balanceBefore = (
      await harness.app.inject({
        method: "GET",
        url: "/api/v1/loyalty/balance",
        headers: { authorization: `Bearer ${accessToken}` }
      })
    ).json() as LoyaltyBalanceResponse;

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/loyalty/redeem",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { points: 500 }
    });
    const body = response.json() as LoyaltyRedeemResponse;

    expect(response.statusCode).toBe(200);
    expect(body.discountPercent).toBe(10);
    expect(body.balanceAfter).toBe(balanceBefore.summary.balance - 500);
  });
});
