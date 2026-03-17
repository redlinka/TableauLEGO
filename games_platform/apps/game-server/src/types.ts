import { AppConfig } from "./config/env.js";
import { TokenService } from "./modules/auth/tokenService.js";
import { PuzzleCatalog } from "./modules/puzzles/puzzleCatalog.js";
import { WebSocketHub } from "./modules/websocket/socketHub.js";
import { RepositoryBundle } from "./storage/mongoose/repositories/index.js";
import { SoloImageRebuildService } from "./domain/image-rebuild/soloSessionService.js";
import { SoloLineBreakerService } from "./domain/line-breaker/soloSessionService.js";
import { DuplicateSessionService } from "./domain/duplicate/duplicateSessionService.js";

export interface AppServices {
  config: AppConfig;
  repositories: RepositoryBundle;
  puzzleCatalog: PuzzleCatalog;
  tokenService: TokenService;
  websocketHub: WebSocketHub;
  imageRebuildSoloService: SoloImageRebuildService;
  lineBreakerSoloService: SoloLineBreakerService;
  duplicateSessionService: DuplicateSessionService;
}
