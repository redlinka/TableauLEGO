import { once } from "node:events";
import { AddressInfo } from "node:net";
import { WebSocket } from "ws";
import { GuestAuthResponse, ServerWsMessage } from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { MongoMemoryServer } from "mongodb-memory-server";
import { buildApp } from "../../src/app.js";
import { DEFAULT_PUZZLE_OUTPUT_DIR } from "../../src/config/paths.js";
import { AppConfig } from "../../src/config/env.js";
import { DuplicateSessionService } from "../../src/domain/duplicate/duplicateSessionService.js";
import { SoloImageRebuildService } from "../../src/domain/image-rebuild/soloSessionService.js";
import { SoloLineBreakerService } from "../../src/domain/line-breaker/soloSessionService.js";
import { TokenService } from "../../src/modules/auth/tokenService.js";
import { PuzzleCatalog } from "../../src/modules/puzzles/puzzleCatalog.js";
import { WebSocketHub } from "../../src/modules/websocket/socketHub.js";
import { connectMongoose, disconnectMongoose } from "../../src/storage/mongoose/connection.js";
import { createRepositories } from "../../src/storage/mongoose/repositories/index.js";

type WaitPredicate = (message: ServerWsMessage) => boolean;

export class WsTestClient {
  readonly messages: ServerWsMessage[] = [];
  private readonly waiters: Array<{
    predicate: WaitPredicate;
    resolve: (message: ServerWsMessage) => void;
  }> = [];

  constructor(readonly socket: WebSocket) {
    socket.on("message", (payload) => {
      const parsed = JSON.parse(payload.toString()) as ServerWsMessage;
      this.messages.push(parsed);

      for (let index = this.waiters.length - 1; index >= 0; index -= 1) {
        const waiter = this.waiters[index];

        if (!waiter.predicate(parsed)) {
          continue;
        }

        this.waiters.splice(index, 1);
        waiter.resolve(parsed);
      }
    });
  }

  send(message: unknown): void {
    this.socket.send(JSON.stringify(message));
  }

  waitFor(
    type: ServerWsMessage["type"],
    predicate?: (message: ServerWsMessage) => boolean,
    timeoutMs = 5_000
  ): Promise<ServerWsMessage> {
    const existing = this.messages.find(
      (message) => message.type === type && (predicate ? predicate(message) : true)
    );

    if (existing) {
      return Promise.resolve(existing);
    }

    return new Promise<ServerWsMessage>((resolve, reject) => {
      const waiter = {
        predicate: (message: ServerWsMessage) =>
          message.type === type && (predicate ? predicate(message) : true),
        resolve: (message: ServerWsMessage) => {
          clearTimeout(timeout);
          resolve(message);
        }
      };
      const timeout = setTimeout(() => {
        const index = this.waiters.indexOf(waiter);

        if (index >= 0) {
          this.waiters.splice(index, 1);
        }

        reject(new Error(`Timed out waiting for WebSocket message '${type}'.`));
      }, timeoutMs);

      this.waiters.push(waiter);
    });
  }

  async close(): Promise<void> {
    if (this.socket.readyState === WebSocket.CLOSED) {
      return;
    }

    if (this.socket.readyState === WebSocket.CLOSING) {
      await this.awaitClose();
      return;
    }

    this.socket.close();
    await this.awaitClose();
  }

  async terminate(): Promise<void> {
    if (this.socket.readyState === WebSocket.CLOSED) {
      return;
    }

    this.socket.terminate();
    await this.awaitClose();
  }

  private async awaitClose(timeoutMs = 2_000): Promise<void> {
    if (this.socket.readyState === WebSocket.CLOSED) {
      return;
    }

    await Promise.race([
      once(this.socket, "close").then(() => undefined),
      new Promise<void>((resolve) => {
        setTimeout(() => {
          if (this.socket.readyState !== WebSocket.CLOSED) {
            this.socket.terminate();
          }

          resolve();
        }, timeoutMs);
      })
    ]);
  }
}

export async function createDuplicateHarness(options?: {
  imageRebuildMaxSequenceLength?: number;
  lineBreakerMaxSequenceLength?: number;
  duplicateTurnLimitMs?: number;
  duplicateReconnectGraceMs?: number;
  duplicateChatMaxLength?: number;
  gameTokenSecret?: string;
  tokenIssuer?: string;
  tokenAudience?: string;
  phpGameTokenSecret?: string;
  phpTokenIssuer?: string;
  phpTokenAudience?: string;
}): Promise<{
  mongoServer: MongoMemoryServer;
  app: FastifyInstance;
  config: AppConfig;
  repositories: ReturnType<typeof createRepositories>;
  duplicateSessionService: DuplicateSessionService;
  websocketHub: WebSocketHub;
  wsUrl: string;
  createGuest: (alias: string) => Promise<GuestAuthResponse>;
  connectClient: (token: string) => Promise<WsTestClient>;
  close: () => Promise<void>;
}> {
  const mongoServer = await MongoMemoryServer.create();
  const clients = new Set<WsTestClient>();
  const config: AppConfig = {
    nodeEnv: "test",
    host: "127.0.0.1",
    port: 3001,
    appVersion: "test",
    corsOrigin: "http://127.0.0.1:5173",
    mongodbUri: mongoServer.getUri(),
    mongodbDbName: "tableaulego_games_duplicate_test",
    gameTokenSecret: options?.gameTokenSecret ?? "phase-d-test-secret",
    phpGameTokenSecret: options?.phpGameTokenSecret ?? "",
    tokenIssuer: options?.tokenIssuer ?? "test-game-server",
    tokenAudience: options?.tokenAudience ?? "test-audience",
    phpTokenIssuer: options?.phpTokenIssuer ?? "test-php",
    phpTokenAudience: options?.phpTokenAudience ?? "test-audience",
    guestTokenTtlSeconds: 3600,
    staticPreviewPrefix: "/assets/puzzle-previews/",
    wsPath: "/ws",
    publicBaseUrl: undefined,
    publicWsUrl: undefined,
    puzzleOutputDir: DEFAULT_PUZZLE_OUTPUT_DIR,
    soloTurnLimitMs: 30_000,
    duplicateTurnLimitMs: options?.duplicateTurnLimitMs ?? 200,
    duplicateReconnectGraceMs: options?.duplicateReconnectGraceMs ?? 120,
    duplicateChatMaxLength: options?.duplicateChatMaxLength ?? 140,
    imageRebuildMaxSequenceLength: options?.imageRebuildMaxSequenceLength ?? 2,
    lineBreakerMaxSequenceLength: options?.lineBreakerMaxSequenceLength ?? 2,
    phpApiUrl: "http://127.0.0.1:19999"
  };

  await connectMongoose(config);

  const repositories = createRepositories();
  const puzzleCatalog = new PuzzleCatalog({
    outputDir: config.puzzleOutputDir,
    previewPrefix: config.staticPreviewPrefix
  });
  await puzzleCatalog.load();

  const tokenService = new TokenService(config);
  const imageRebuildSoloService = new SoloImageRebuildService({
    config,
    repositories,
    puzzleCatalog
  });
  const lineBreakerSoloService = new SoloLineBreakerService({
    config,
    repositories
  });
  const duplicateSessionService = new DuplicateSessionService({
    config,
    repositories,
    puzzleCatalog
  });
  const websocketHub = new WebSocketHub(
    config,
    repositories,
    tokenService,
    duplicateSessionService
  );
  duplicateSessionService.setNotifier(websocketHub);

  const app = await buildApp({
    config,
    repositories,
    puzzleCatalog,
    tokenService,
    websocketHub,
    imageRebuildSoloService,
    lineBreakerSoloService,
    duplicateSessionService
  });
  websocketHub.attach(app.server);
  await app.listen({
    host: config.host,
    port: 0
  });

  const address = app.server.address() as AddressInfo;
  const wsUrl = `ws://${config.host}:${address.port}${config.wsPath}`;

  return {
    mongoServer,
    app,
    config,
    repositories,
    duplicateSessionService,
    websocketHub,
    wsUrl,
    async createGuest(alias: string) {
      const response = await app.inject({
        method: "POST",
        url: "/api/v1/auth/guest",
        payload: {
          preferredAlias: alias
        }
      });

      return response.json() as GuestAuthResponse;
    },
    async connectClient(token: string) {
      const socket = new WebSocket(wsUrl);
      await once(socket, "open");
      const client = new WsTestClient(socket);
      clients.add(client);
      client.send({
        type: "session.authenticate",
        requestId: crypto.randomUUID(),
        payload: {
          token
        }
      });
      await client.waitFor("session.authenticated");
      return client;
    },
    async close() {
      await Promise.allSettled(
        [...clients].map(async (client) => {
          try {
            await client.terminate();
          } finally {
            clients.delete(client);
          }
        })
      );
      await duplicateSessionService.close();
      await websocketHub.close();
      await app.close();
      await disconnectMongoose();
      await mongoServer.stop();
    }
  };
}
