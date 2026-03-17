import { buildApp } from "./app.js";
import { loadConfig } from "./config/env.js";
import { DuplicateSessionService } from "./domain/duplicate/duplicateSessionService.js";
import { SoloImageRebuildService } from "./domain/image-rebuild/soloSessionService.js";
import { SoloLineBreakerService } from "./domain/line-breaker/soloSessionService.js";
import { TokenService } from "./modules/auth/tokenService.js";
import { PuzzleCatalog } from "./modules/puzzles/puzzleCatalog.js";
import { WebSocketHub } from "./modules/websocket/socketHub.js";
import { connectMongoose, disconnectMongoose } from "./storage/mongoose/connection.js";
import { createRepositories } from "./storage/mongoose/repositories/index.js";

async function main(): Promise<void> {
  const config = loadConfig();
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

  app.addHook("onClose", async () => {
    await websocketHub.close();
    await duplicateSessionService.close();
    await disconnectMongoose();
  });

  let shuttingDown = false;
  const shutdown = async (signal: string) => {
    if (shuttingDown) {
      return;
    }

    shuttingDown = true;
    app.log.info({ signal }, "Shutting down game server");

    try {
      await app.close();
    } finally {
      process.exitCode = 0;
    }
  };

  process.on("SIGINT", () => {
    void shutdown("SIGINT");
  });
  process.on("SIGTERM", () => {
    void shutdown("SIGTERM");
  });

  await app.listen({
    host: config.host,
    port: config.port
  });
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
