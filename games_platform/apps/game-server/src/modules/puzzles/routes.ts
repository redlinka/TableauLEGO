import { PuzzleDetailResponse, PuzzleListResponse } from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { AppServices } from "../../types.js";

function buildBaseUrl(request: {
  headers: Record<string, unknown>;
  protocol: string;
}, fallbackHost: string, publicBaseUrl?: string): string {
  if (publicBaseUrl) {
    return publicBaseUrl;
  }

  const host = typeof request.headers.host === "string" ? request.headers.host : fallbackHost;
  return `${request.protocol}://${host}`;
}

export async function registerPuzzleRoutes(
  app: FastifyInstance,
  services: AppServices
): Promise<void> {
  app.get("/api/v1/puzzles", async (request) => {
    const baseUrl = buildBaseUrl(
      request,
      `${services.config.host}:${services.config.port}`,
      services.config.publicBaseUrl
    );

    const response: PuzzleListResponse = {
      total: services.puzzleCatalog.size,
      items: services.puzzleCatalog.list(baseUrl)
    };

    return response;
  });

  app.get<{ Params: { id: string } }>("/api/v1/puzzles/:id", async (request, reply) => {
    const baseUrl = buildBaseUrl(
      request,
      `${services.config.host}:${services.config.port}`,
      services.config.publicBaseUrl
    );
    const puzzle = services.puzzleCatalog.getById(request.params.id, baseUrl);

    if (!puzzle) {
      return reply.code(404).send({
        error: {
          code: "PUZZLE_NOT_FOUND",
          message: `Unknown puzzle '${request.params.id}'.`
        }
      });
    }

    const response: PuzzleDetailResponse = {
      item: puzzle
    };

    return response;
  });
}

