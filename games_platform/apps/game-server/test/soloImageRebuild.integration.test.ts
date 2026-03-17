import {
  INITIAL_BRICK_SHAPES,
  PuzzleListResponse,
  SoloImageRebuildResultResponse,
  SoloImageRebuildSessionResponse,
  SubmitSoloImageRebuildPlacementResponse
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

describe("solo image_rebuild integration", () => {
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
      mongodbDbName: "tableaulego_games_test",
      gameTokenSecret: "phase-b-test-secret",
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
      payload: {
        preferredAlias: "PhaseBTester"
      }
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

  it("creates, plays and finalizes a solo session while persisting history", async () => {
    const puzzlesResponse = await app.inject({
      method: "GET",
      url: "/api/v1/puzzles"
    });
    const puzzlesBody = puzzlesResponse.json() as PuzzleListResponse;
    const puzzleId = puzzlesBody.items[0]?.id;

    expect(puzzleId).toBeTruthy();

    if (!puzzleId) {
      throw new Error("Expected at least one puzzle in the catalog for integration testing.");
    }

    const createResponse = await app.inject({
      method: "POST",
      url: "/api/v1/solo/image-rebuild/sessions",
      headers: {
        authorization: `Bearer ${accessToken}`
      },
      payload: {
        puzzleId,
        seed: "integration-seed"
      }
    });
    const created = createResponse.json() as SoloImageRebuildSessionResponse;

    expect(createResponse.statusCode).toBe(201);
    expect(created.state.session.status).toBe("active");
    expect(created.state.currentOffer).not.toBeNull();

    let currentState = created.state;
    let safetyCounter = 0;

    while (!currentState.finished && currentState.currentOffer && safetyCounter < 12) {
      const shape = INITIAL_BRICK_SHAPES.find(
        (entry) => entry.shapeId === currentState.currentOffer?.shapeId
      );

      expect(shape).toBeDefined();

      const placements = listValidPlacementsForShape(
        hydrateBoardState(currentState.board),
        shape!
      );

      expect(placements.length).toBeGreaterThan(0);

      const placementResponse = await app.inject({
        method: "POST",
        url: `/api/v1/solo/image-rebuild/sessions/${currentState.session.sessionId}/placements`,
        headers: {
          authorization: `Bearer ${accessToken}`
        },
        payload: {
          anchorX: placements[0].anchorX,
          anchorY: placements[0].anchorY,
          rotation: placements[0].rotation
        }
      });
      const placed = placementResponse.json() as SubmitSoloImageRebuildPlacementResponse;

      expect(placementResponse.statusCode).toBe(200);
      expect(placed.accepted).toBe(true);

      currentState = placed.state;
      safetyCounter += 1;
    }

    expect(currentState.finished).toBe(true);
    expect(["sequence_exhausted", "no_more_moves"]).toContain(currentState.finishReason);
    expect(currentState.result?.score).toBeTypeOf("number");

    const resultResponse = await app.inject({
      method: "GET",
      url: `/api/v1/solo/image-rebuild/sessions/${currentState.session.sessionId}/result`,
      headers: {
        authorization: `Bearer ${accessToken}`
      }
    });
    const result = resultResponse.json() as SoloImageRebuildResultResponse;

    expect(resultResponse.statusCode).toBe(200);
    expect(result.finished).toBe(true);
    expect(result.result?.score).toBe(currentState.result?.score);

    const historyResponse = await app.inject({
      method: "GET",
      url: "/api/v1/history",
      headers: {
        authorization: `Bearer ${accessToken}`
      }
    });

    expect(historyResponse.statusCode).toBe(200);
    expect(historyResponse.json()).toMatchObject({
      total: 1,
      items: [
        {
          sessionId: currentState.session.sessionId,
          gameType: "image_rebuild",
          mode: "solo"
        }
      ]
    });
  }, 120_000);
});
