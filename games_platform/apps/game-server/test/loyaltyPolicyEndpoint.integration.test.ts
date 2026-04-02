import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";

describe("loyalty policy endpoint via bootstrap", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;

  beforeAll(async () => {
    harness = await createDuplicateHarness();
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("bootstrap returns loyalty tiers even when PHP policy is unreachable", async () => {
    const response = await harness.app.inject({
      method: "GET",
      url: "/api/v1/bootstrap"
    });
    const body = response.json();

    expect(response.statusCode).toBe(200);
    expect(body.loyalty).toBeDefined();
    expect(body.loyalty.tiers).toBeDefined();
    expect(Array.isArray(body.loyalty.tiers)).toBe(true);
    expect(body.loyalty.tiers.length).toBeGreaterThan(0);
  });

  it("bootstrap loyalty tiers have correct structure", async () => {
    const response = await harness.app.inject({
      method: "GET",
      url: "/api/v1/bootstrap"
    });
    const body = response.json();

    for (const tier of body.loyalty.tiers) {
      expect(tier).toHaveProperty("points");
      expect(tier).toHaveProperty("discountPercent");
      expect(typeof tier.points).toBe("number");
      expect(typeof tier.discountPercent).toBe("number");
      expect(tier.points).toBeGreaterThan(0);
      expect(tier.discountPercent).toBeGreaterThan(0);
    }
  });

  it("bootstrap returns maxDiscountPercent", async () => {
    const response = await harness.app.inject({
      method: "GET",
      url: "/api/v1/bootstrap"
    });
    const body = response.json();

    expect(body.loyalty.maxDiscountPercent).toBeDefined();
    expect(typeof body.loyalty.maxDiscountPercent).toBe("number");
    expect(body.loyalty.maxDiscountPercent).toBeGreaterThan(0);
  });

  it("redeem endpoint falls back to default tiers when PHP unreachable", async () => {
    const auth = await harness.createGuest("PolicyFallback");

    await harness.repositories.players.applyLoyaltyDelta({
      playerId: auth.player.playerId,
      pointsDelta: 300
    });

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/loyalty/redeem",
      headers: { authorization: `Bearer ${auth.accessToken}` },
      payload: { points: 200 }
    });

    expect(response.statusCode).toBe(200);
    expect(response.json().discountPercent).toBe(5);
  });
});
