import { createHmac } from "node:crypto";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";

function base64url(data: Buffer): string {
  return data.toString("base64url");
}

function createPhpJwt(
  payload: Record<string, unknown>,
  secret: string
): string {
  const header = base64url(
    Buffer.from(JSON.stringify({ alg: "HS256", typ: "JWT" }))
  );
  const body = base64url(Buffer.from(JSON.stringify(payload)));
  const signature = base64url(
    createHmac("sha256", secret).update(`${header}.${body}`).digest()
  );

  return `${header}.${body}.${signature}`;
}

describe("PHP bridge authentication contract", () => {
  const PHP_SECRET = "test-php-secret-at-least-32-chars!";
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;

  beforeAll(async () => {
    harness = await createDuplicateHarness({
      phpGameTokenSecret: PHP_SECRET,
      phpTokenIssuer: "tableaulego-php",
      phpTokenAudience: "tableaulego-games"
    });
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("accepts a valid PHP JWT and returns a Node access token", async () => {
    const now = Math.floor(Date.now() / 1000);
    const phpToken = createPhpJwt(
      {
        iss: "tableaulego-php",
        aud: "tableaulego-games",
        sub: "42",
        alias: "TestUser",
        loyaltyId: "42",
        iat: now,
        exp: now + 300
      },
      PHP_SECRET
    );

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken }
    });

    expect(response.statusCode).toBe(200);

    const body = response.json();

    expect(body.accessToken).toBeDefined();
    expect(body.tokenType).toBe("Bearer");
    expect(body.player).toBeDefined();
    expect(body.player.authSource).toBe("php");
    expect(body.bridge).toBeDefined();
    expect(body.bridge.issuer).toBe("tableaulego-php");
    expect(body.bridge.subjectHash).toBeDefined();
  });

  it("rejects an expired PHP JWT", async () => {
    const now = Math.floor(Date.now() / 1000);
    const phpToken = createPhpJwt(
      {
        iss: "tableaulego-php",
        aud: "tableaulego-games",
        sub: "42",
        iat: now - 600,
        exp: now - 300
      },
      PHP_SECRET
    );

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken }
    });

    expect(response.statusCode).toBe(401);
  });

  it("rejects a JWT signed with wrong secret", async () => {
    const now = Math.floor(Date.now() / 1000);
    const phpToken = createPhpJwt(
      {
        iss: "tableaulego-php",
        aud: "tableaulego-games",
        sub: "42",
        iat: now,
        exp: now + 300
      },
      "wrong-secret-that-is-also-32-chars!"
    );

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken }
    });

    expect(response.statusCode).toBe(401);
  });

  it("rejects a JWT with wrong issuer", async () => {
    const now = Math.floor(Date.now() / 1000);
    const phpToken = createPhpJwt(
      {
        iss: "wrong-issuer",
        aud: "tableaulego-games",
        sub: "42",
        iat: now,
        exp: now + 300
      },
      PHP_SECRET
    );

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken }
    });

    expect(response.statusCode).toBe(401);
  });

  it("rejects a JWT with wrong audience", async () => {
    const now = Math.floor(Date.now() / 1000);
    const phpToken = createPhpJwt(
      {
        iss: "tableaulego-php",
        aud: "wrong-audience",
        sub: "42",
        iat: now,
        exp: now + 300
      },
      PHP_SECRET
    );

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken }
    });

    expect(response.statusCode).toBe(401);
  });

  it("pseudonymizes the PHP subject (does not store raw userId)", async () => {
    const now = Math.floor(Date.now() / 1000);
    const phpToken = createPhpJwt(
      {
        iss: "tableaulego-php",
        aud: "tableaulego-games",
        sub: "9999",
        alias: "PseudoTest",
        loyaltyId: "9999",
        iat: now,
        exp: now + 300
      },
      PHP_SECRET
    );

    const response = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken }
    });
    const body = response.json();

    expect(body.player.playerId).not.toContain("9999");
    expect(body.bridge.subjectHash).not.toBe("9999");
  });

  it("same PHP user always gets the same playerId", async () => {
    const now = Math.floor(Date.now() / 1000);
    const makeToken = () =>
      createPhpJwt(
        {
          iss: "tableaulego-php",
          aud: "tableaulego-games",
          sub: "stable-user-777",
          alias: "StableUser",
          loyaltyId: "777",
          iat: now,
          exp: now + 300
        },
        PHP_SECRET
      );

    const response1 = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: makeToken() }
    });
    const response2 = await harness.app.inject({
      method: "POST",
      url: "/api/v1/auth/php/exchange",
      payload: { phpToken: makeToken() }
    });

    expect(response1.json().player.playerId).toBe(
      response2.json().player.playerId
    );
  });
});
