import { HealthResponse } from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { getMongooseState } from "../../storage/mongoose/connection.js";
import { AppServices } from "../../types.js";

export async function registerHealthRoutes(
  app: FastifyInstance,
  services: AppServices
): Promise<void> {
  app.get("/health", async () => {
    const response: HealthResponse = {
      status: "ok",
      service: "game-server",
      version: services.config.appVersion,
      serverTime: new Date().toISOString(),
      database: {
        state: getMongooseState()
      },
      puzzlesLoaded: services.puzzleCatalog.size
    };

    return response;
  });
}

