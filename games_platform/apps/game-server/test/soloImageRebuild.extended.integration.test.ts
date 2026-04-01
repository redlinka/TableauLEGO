import {
  INITIAL_BRICK_SHAPES,
  PuzzleListResponse,
  SoloImageRebuildSessionResponse
} from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { MongoMemoryServer } from "mongodb-memory-server";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import { DEFAULT_PUZZLE_OUTPUT_DIR } from "../src/config/paths.js";
import { AppConfig } from "../src/config/env.js";
import { hydrateBoardState, listValidPlacementsForShape } from "../src/domain/common/boardEngine.js";
import { SoloImageRebuildService } from "../src/domain/image-rebuild/soloSessionService.js";
import { SoloLineBreakerService } from "../src/domain/line-breaker/soloSessionService.js";
import { TokenService } from "../src/modules/auth/tokenService.js";
import { PuzzleCatalog } from "../src/modules/puzzles/puzzleCatalog.js";
import { WebSocketHub } from "../src/modules/websocket/socketHub.js";
import { connectMongoose, disconnectMongoose } from "../src/storage/mongoose/connection.js";
import { createRepositories } from "../src/storage/mongoose/repositories/index.js";

describe("solo image_rebuild – extended integration", () => {
  let mongoServer: MongoMemoryServer;
  let app: FastifyInstance;
  let accessToken = "";
  let puzzleId = "";

  beforeAll(async () => {
    mongoServer = await MongoMemoryServer.create();

    const config: AppConfig = {
      nodeEnv: "test",
      host: "127.0.0.1",
      port: 3001,
      appVersion: "test",
      corsOrigin: "http://127.0.0.1:5173",
      mongodbUri: mongoServer.getUri(),
      mongodbDbName: "tableaulego_solo_ir_ext_test",
      gameTokenSecret: "ext-test-secret",
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
      imageRebuildMaxSequenceLength: 4,
      lineBreakerMaxSequenceLength: 4
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
      payload: { preferredAlias: "ExtTester" }
    });
    accessToken = authResponse.json().accessToken as string;

    const puzzlesResponse = await app.inject({
      method: "GET",
      url: "/api/v1/puzzles"
    });
    puzzleId = (puzzlesResponse.json() as PuzzleListResponse).items[0]?.id ?? "";
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

  it("rejects a placement with out-of-bounds coordinates", async () => {
    const createResponse = await app.inject({
      method: "POST",
      url: "/api/v1/solo/image-rebuild/sessions",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { puzzleId, seed: "invalid-placement-seed" }
    });
    const created = createResponse.json() as SoloImageRebuildSessionResponse;

    expect(createResponse.statusCode).toBe(201);

    const placementResponse = await app.inject({
      method: "POST",
      url: `/api/v1/solo/image-rebuild/sessions/${created.state.session.sessionId}/placements`,
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { anchorX: 99, anchorY: 99, rotation: 0 }
    });

    expect(placementResponse.statusCode).toBe(200);
    expect(placementResponse.json().accepted).toBe(false);
  }, 120_000);

  it("abandons a session and receives an abandoned result", async () => {
    const createResponse = await app.inject({
      method: "POST",
      url: "/api/v1/solo/image-rebuild/sessions",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { puzzleId, seed: "abandon-seed" }
    });
    const created = createResponse.json() as SoloImageRebuildSessionResponse;

    expect(createResponse.statusCode).toBe(201);

    const abandonResponse = await app.inject({
      method: "POST",
      url: `/api/v1/solo/image-rebuild/sessions/${created.state.session.sessionId}/abandon`,
      headers: { authorization: `Bearer ${accessToken}` }
    });

    expect(abandonResponse.statusCode).toBe(200);

    const result = abandonResponse.json();

    expect(result.state.finished).toBe(true);
    expect(result.state.finishReason).toBe("abandoned");
  }, 120_000);

  it("returns 404 for a non-existent session", async () => {
    const response = await app.inject({
      method: "GET",
      url: "/api/v1/solo/image-rebuild/sessions/ses_nonexistent/result",
      headers: { authorization: `Bearer ${accessToken}` }
    });

    expect(response.statusCode).toBe(404);
  }, 120_000);

  it("returns 401 when no auth token is provided", async () => {
    const response = await app.inject({
      method: "POST",
      url: "/api/v1/solo/image-rebuild/sessions",
      payload: { puzzleId, seed: "noauth" }
    });

    expect(response.statusCode).toBe(401);
  }, 120_000);

  it("retrieves session state mid-game", async () => {
    const createResponse = await app.inject({
      method: "POST",
      url: "/api/v1/solo/image-rebuild/sessions",
      headers: { authorization: `Bearer ${accessToken}` },
      payload: { puzzleId, seed: "midgame-seed" }
    });
    const created = createResponse.json() as SoloImageRebuildSessionResponse;
    const sessionId = created.state.session.sessionId;

    const shape = INITIAL_BRICK_SHAPES.find(
      (entry) => entry.shapeId === created.state.currentOffer?.shapeId
    );
    const placements = listValidPlacementsForShape(
      hydrateBoardState(created.state.board),
      shape!
    );

    await app.inject({
      method: "POST",
      url: `/api/v1/solo/image-rebuild/sessions/${sessionId}/placements`,
      headers: { authorization: `Bearer ${accessToken}` },
      payload: {
        anchorX: placements[0].anchorX,
        anchorY: placements[0].anchorY,
        rotation: placements[0].rotation
      }
    });

    const stateResponse = await app.inject({
      method: "GET",
      url: `/api/v1/solo/image-rebuild/sessions/${sessionId}`,
      headers: { authorization: `Bearer ${accessToken}` }
    });

    expect(stateResponse.statusCode).toBe(200);

    const state = stateResponse.json().state;

    expect(state.session.status).toBe("active");
    expect(state.board.occupiedCount).toBeGreaterThan(0);
    expect(state.turn.index).toBeGreaterThanOrEqual(2);
  }, 120_000);
});
