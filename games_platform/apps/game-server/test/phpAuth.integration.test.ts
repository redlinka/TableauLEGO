import {
  BootstrapResponse,
  LoyaltyBalanceResponse,
  PhpExchangeResponse
} from "@games-platform/game-contracts";
import { SignJWT } from "jose";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";

const encoder = new TextEncoder();

describe("php signed auth bridge", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;

  beforeAll(async () => {
    harness = await createDuplicateHarness({
      phpGameTokenSecret: "phase-e-php-secret",
      phpTokenIssuer: "tableaulego-php-test",
      phpTokenAudience: "tableaulego-games-test",
      tokenIssuer: "tableaulego-node-test",
      tokenAudience: "tableaulego-games-test",
      gameTokenSecret: "phase-e-node-secret"
    });
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("exchanges a PHP-signed token into a technical Node token without storing PII", async () => {
    const phpToken = await new SignJWT({
      alias: "PHP Member",
      loyaltyId: "CARD-12345"
    })
      .setProtectedHeader({ alg: "HS256" })
      .setIssuedAt()
      .setIssuer("tableaulego-php-test")
      .setAudience("tableaulego-games-test")
      .setSubject("legacy-user-42")
      .setExpirationTime("5m")
      .sign(encoder.encode("phase-e-php-secret"));

    const exchangeResponse = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: {
        phpToken
      }
    });
    const exchanged = exchangeResponse.json() as PhpExchangeResponse;

    expect(exchangeResponse.statusCode).toBe(200);
    expect(exchanged.player.authSource).toBe("php");
    expect(exchanged.player.playerId.startsWith("php_")).toBe(true);
    expect(exchanged.bridge.subjectHash).not.toContain("legacy-user-42");
    expect(exchanged.bridge.technicalLoyaltyId?.startsWith("loy_")).toBe(true);

    const bootstrapResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/bootstrap",
      headers: {
        authorization: `Bearer ${exchanged.accessToken}`
      }
    });
    const bootstrap = bootstrapResponse.json() as BootstrapResponse;

    expect(bootstrapResponse.statusCode).toBe(200);
    expect(bootstrap.auth.phpTokenValidationConfigured).toBe(true);
    expect(bootstrap.auth.currentPlayer).toMatchObject({
      playerId: exchanged.player.playerId,
      authSource: "php"
    });

    const balanceResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/loyalty/balance",
      headers: {
        authorization: `Bearer ${exchanged.accessToken}`
      }
    });
    const balance = balanceResponse.json() as LoyaltyBalanceResponse;

    expect(balanceResponse.statusCode).toBe(200);
    expect(balance.summary.playerId).toBe(exchanged.player.playerId);
    expect(balance.summary.technicalLoyaltyId).toBe(exchanged.bridge.technicalLoyaltyId);
    expect(balance.summary.balance).toBe(0);
  });
});
