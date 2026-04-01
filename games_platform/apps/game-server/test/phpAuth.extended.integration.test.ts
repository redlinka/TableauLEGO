import { SignJWT } from "jose";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";

const encoder = new TextEncoder();

describe("php auth bridge – extended", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;

  beforeAll(async () => {
    harness = await createDuplicateHarness({
      phpGameTokenSecret: "ext-php-secret",
      phpTokenIssuer: "ext-php-test",
      phpTokenAudience: "ext-games-test",
      tokenIssuer: "ext-node-test",
      tokenAudience: "ext-games-test",
      gameTokenSecret: "ext-node-secret"
    });
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("rejects an expired PHP token", async () => {
    const expired = await new SignJWT({ alias: "Expired" })
      .setProtectedHeader({ alg: "HS256" })
      .setIssuedAt(Math.floor(Date.now() / 1000) - 600)
      .setIssuer("ext-php-test")
      .setAudience("ext-games-test")
      .setSubject("expired-user")
      .setExpirationTime(Math.floor(Date.now() / 1000) - 300)
      .sign(encoder.encode("ext-php-secret"));

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: expired }
    });

    expect(response.statusCode).toBeGreaterThanOrEqual(400);
  });

  it("rejects a token signed with the wrong secret", async () => {
    const wrongSecret = await new SignJWT({ alias: "WrongKey" })
      .setProtectedHeader({ alg: "HS256" })
      .setIssuedAt()
      .setIssuer("ext-php-test")
      .setAudience("ext-games-test")
      .setSubject("wrong-key-user")
      .setExpirationTime("5m")
      .sign(encoder.encode("WRONG-SECRET"));

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: wrongSecret }
    });

    expect(response.statusCode).toBeGreaterThanOrEqual(400);
  });

  it("rejects a token with a wrong issuer", async () => {
    const wrongIssuer = await new SignJWT({ alias: "WrongIssuer" })
      .setProtectedHeader({ alg: "HS256" })
      .setIssuedAt()
      .setIssuer("not-the-expected-issuer")
      .setAudience("ext-games-test")
      .setSubject("wrong-issuer-user")
      .setExpirationTime("5m")
      .sign(encoder.encode("ext-php-secret"));

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: wrongIssuer }
    });

    expect(response.statusCode).toBeGreaterThanOrEqual(400);
  });

  it("rejects a malformed/garbage token string", async () => {
    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: "this-is-not-a-jwt" }
    });

    expect(response.statusCode).toBeGreaterThanOrEqual(400);
  });

  it("returns the same playerId for the same PHP subject on repeated exchanges", async () => {
    const makeToken = () =>
      new SignJWT({ alias: "Repeat User" })
        .setProtectedHeader({ alg: "HS256" })
        .setIssuedAt()
        .setIssuer("ext-php-test")
        .setAudience("ext-games-test")
        .setSubject("stable-user-77")
        .setExpirationTime("5m")
        .sign(encoder.encode("ext-php-secret"));

    const response1 = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: await makeToken() }
    });
    const response2 = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: await makeToken() }
    });

    expect(response1.statusCode).toBe(200);
    expect(response2.statusCode).toBe(200);
    expect(response1.json().player.playerId).toBe(response2.json().player.playerId);
  });
});
