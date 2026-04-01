import { BootstrapResponse } from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { MongoMemoryServer } from "mongodb-memory-server";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import { DEFAULT_PUZZLE_OUTPUT_DIR } from "../src/config/paths.js";
import { AppConfig } from "../src/config/env.js";
import { SoloImageRebuildService } from "../src/domain/image-rebuild/soloSessionService.js";
import { SoloLineBreakerService } from "../src/domain/line-breaker/soloSessionService.js";
import { TokenService } from "../src/modules/auth/tokenService.js";
import { PuzzleCatalog } from "../src/modules/puzzles/puzzleCatalog.js";
import { WebSocketHub } from "../src/modules/websocket/socketHub.js";
import { connectMongoose, disconnectMongoose } from "../src/storage/mongoose/connection.js";
import { createRepositories } from "../src/storage/mongoose/repositories/index.js";

describe("health and bootstrap endpoints", () => {
  let mongoServer: MongoMemoryServer;
  let app: FastifyInstance;
  let accessToken = "";

  beforeAll(async () => {
    mongoServer = await MongoMemoryServer.create();

    const config: AppConfig = {
      nodeEnv: "test",
      host: "127.0.0.1",
      port: 3001,
      appVersion: "test",
      corsOrigin: "http://127.0.0.1:5173",
      mongodbUri: mongoServer.getUri(),
      mongodbDbName: "tableaulego_health_test",
      gameTokenSecret: "health-test-secret",
      phpGameTokenSecret: "",
      tokenIssuer: "test-game-server",
      tokenAudience: "test-audience",
      phpTokenIssuer: "test-php",
      phpTokenAudience: "test-audience",
      guestTokenTtlSeconds: 3600,
      staticPreviewPrefix: "/assets/puzzle-previews/",
      wsPath: "/ws",
      publicBaseUrl: "http://127.0.0.1:3001",
      publicWsUrl: "ws://127.0.0.1:3001/ws",
      puzzleOutputDir: DEFAULT_PUZZLE_OUTPUT_DIR,
      soloTurnLimitMs: 30000,
      duplicateTurnLimitMs: 30000,
      duplicateReconnectGraceMs: 60000,
      duplicateChatMaxLength: 240,
      imageRebuildMaxSequenceLength: 6,
      lineBreakerMaxSequenceLength: 8
    };

    await connectMongoose(config);

    const repositories = createRepositories();
    const puzzleCatalog = new PuzzleCatalog({
      outputDir: config.puzzleOutputDir,
      previewPrefix: config.staticPreviewPrefix
    });
    await puzzleCatalog.load();

    const tokenService = new TokenService(config);
    const websocketHub = new WebSocketHub(config, repositories, tokenService);
    const imageRebuildSoloService = new SoloImageRebuildService({
      config,
      repositories,
      puzzleCatalog
    });
    const lineBreakerSoloService = new SoloLineBreakerService({
      config,
      repositories
    });

    app = await buildApp({
      config,
      repositories,
      puzzleCatalog,
      tokenService,
      websocketHub,
      imageRebuildSoloService,
      lineBreakerSoloService
    });
    await app.ready();

    const authResponse = await app.inject({
      method: "POST",
      url: "/api/v1/auth/guest",
      payload: { preferredAlias: "HealthTester" }
    });
    accessToken = authResponse.json().accessToken as string;
  }, 300_000);

  afterAll(async () => {
    if (app) {
      await app.close();
    }

    await disconnectMongoose();

    if (mongoServer) {
      await mongoServer.stop();
    }
  }, 60_000);

  it("returns 200 on health check", async () => {
    const response = await app.inject({
      method: "GET",
      url: "/health"
    });

    expect(response.statusCode).toBe(200);
    expect(response.json()).toMatchObject({ status: "ok" });
  });

  it("returns bootstrap data without authentication", async () => {
    const response = await app.inject({
      method: "GET",
      url: "/api/v1/bootstrap"
    });

    expect(response.statusCode).toBe(200);

    const body = response.json() as BootstrapResponse;

    expect(body.games).toBeDefined();
    expect(body.bricks).toBeDefined();
    expect(body.puzzles).toBeDefined();
  });

  it("returns enriched bootstrap data with authentication", async () => {
    const response = await app.inject({
      method: "GET",
      url: "/api/v1/bootstrap",
      headers: { authorization: `Bearer ${accessToken}` }
    });

    expect(response.statusCode).toBe(200);

    const body = response.json() as BootstrapResponse;

    expect(body.auth.currentPlayer).toBeDefined();
    expect(body.auth.currentPlayer?.authSource).toBe("guest");
  });

  it("lists puzzles from the catalog", async () => {
    const response = await app.inject({
      method: "GET",
      url: "/api/v1/puzzles"
    });

    expect(response.statusCode).toBe(200);
    expect(response.json().items.length).toBeGreaterThanOrEqual(1);
  });

  it("returns puzzle detail by id", async () => {
    const listResponse = await app.inject({
      method: "GET",
      url: "/api/v1/puzzles"
    });
    const puzzleId = listResponse.json().items[0]?.id;

    expect(puzzleId).toBeTruthy();

    const detailResponse = await app.inject({
      method: "GET",
      url: `/api/v1/puzzles/${puzzleId}`
    });

    expect(detailResponse.statusCode).toBe(200);
    expect(detailResponse.json().item.id).toBe(puzzleId);
    expect(detailResponse.json().item.cells).toBeDefined();
  });

  it("creates a guest account with the preferred alias", async () => {
    const response = await app.inject({
      method: "POST",
      url: "/api/v1/auth/guest",
      payload: { preferredAlias: "TestAlias" }
    });

    expect(response.statusCode).toBe(200);
    expect(response.json().player.publicAlias).toBe("TestAlias");
    expect(response.json().player.authSource).toBe("guest");
    expect(response.json().accessToken).toBeTruthy();
  });
});
