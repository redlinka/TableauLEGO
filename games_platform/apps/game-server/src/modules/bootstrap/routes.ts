import {
  AuthSource,
  BootstrapResponse,
  GameMode,
  GameType,
  INITIAL_BRICK_SHAPES
} from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { AppConfig } from "../../config/env.js";
import { AppServices } from "../../types.js";
import { resolveAuthenticatedPlayer } from "../auth/requestAuth.js";

function normalizeBaseUrl(requestBaseUrl: string | undefined, fallback: string): string {
  return requestBaseUrl && requestBaseUrl.trim() ? requestBaseUrl : fallback;
}

function buildBaseUrl(appConfig: AppConfig, hostHeader: string, protocol: string): string {
  return normalizeBaseUrl(appConfig.publicBaseUrl, `${protocol}://${hostHeader}`);
}

function buildWsUrl(appConfig: AppConfig, hostHeader: string, protocol: string): string {
  if (appConfig.publicWsUrl) {
    return appConfig.publicWsUrl;
  }

  const wsProtocol = protocol === "https" ? "wss" : "ws";
  return `${wsProtocol}://${hostHeader}${appConfig.wsPath}`;
}

export async function registerBootstrapRoutes(
  app: FastifyInstance,
  services: AppServices
): Promise<void> {
  app.get("/api/v1/bootstrap", async (request) => {
    const currentPlayer = await resolveAuthenticatedPlayer(request, services);
    const hostHeader = request.headers.host ?? `${services.config.host}:${services.config.port}`;
    const baseUrl = buildBaseUrl(services.config, hostHeader, request.protocol);
    const websocketUrl = buildWsUrl(services.config, hostHeader, request.protocol);

    const response: BootstrapResponse = {
      service: {
        name: "tableaulego-games",
        version: services.config.appVersion,
        environment: services.config.nodeEnv,
        serverTime: new Date().toISOString(),
        websocketUrl
      },
      auth: {
        supportedSources: [AuthSource.Guest, AuthSource.Php],
        guestEnabled: true,
        phpTokenValidationConfigured: Boolean(services.config.phpGameTokenSecret),
        currentPlayer
      },
      games: [
        {
          type: GameType.ImageRebuild,
          label: "Reproduction d'image",
          supportedModes: [GameMode.Solo, GameMode.Duplicate2P]
        },
        {
          type: GameType.LineBreaker,
          label: "Casse-briques de lignes",
          supportedModes: [GameMode.Solo, GameMode.Duplicate2P]
        }
      ],
      puzzles: {
        total: services.puzzleCatalog.size,
        items: services.puzzleCatalog.list(baseUrl)
      },
      bricks: {
        totalShapes: INITIAL_BRICK_SHAPES.length,
        shapes: INITIAL_BRICK_SHAPES
      },
      loyalty: {
        tiers: [
          { points: 200, discountPercent: 5 },
          { points: 500, discountPercent: 10 },
          { points: 1000, discountPercent: 20 }
        ],
        maxDiscountPercent: 50
      }
    };

    return response;
  });
}

