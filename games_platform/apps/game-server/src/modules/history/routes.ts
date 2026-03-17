import {
  GameMode,
  GameType,
  HistoryDetailResponse,
  HistoryResponse
} from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { z } from "zod";
import { AppServices } from "../../types.js";
import { resolveAuthenticatedPlayer } from "../auth/requestAuth.js";

const historyQuerySchema = z.object({
  limit: z.coerce.number().int().min(1).max(100).default(20),
  gameType: z.nativeEnum(GameType).optional(),
  mode: z.nativeEnum(GameMode).optional()
});

const historyParamsSchema = z.object({
  historyId: z.string().trim().min(1)
});

export async function registerHistoryRoutes(
  app: FastifyInstance,
  services: AppServices
): Promise<void> {
  app.get("/api/v1/history", async (request) => {
    const query = historyQuerySchema.parse(request.query ?? {});
    const player = await resolveAuthenticatedPlayer(request, services);

    if (!player) {
      const emptyResponse: HistoryResponse = {
        total: 0,
        items: [],
        filters: {
          limit: query.limit,
          gameType: query.gameType,
          mode: query.mode
        }
      };

      return emptyResponse;
    }

    const items = await services.repositories.history.listByPlayerIdFiltered({
      playerId: player.playerId,
      limit: query.limit,
      gameType: query.gameType,
      mode: query.mode
    });
    const response: HistoryResponse = {
      total: items.length,
      items,
      filters: {
        limit: query.limit,
        gameType: query.gameType,
        mode: query.mode
      }
    };

    return response;
  });

  app.get("/api/v1/history/:historyId", async (request, reply) => {
    const player = await resolveAuthenticatedPlayer(request, services);

    if (!player) {
      return reply.code(401).send({
        error: {
          code: "AUTH_REQUIRED",
          message: "Authenticate before requesting history details."
        }
      });
    }

    const { historyId } = historyParamsSchema.parse(request.params ?? {});
    const item = await services.repositories.history.findByHistoryId(player.playerId, historyId);

    if (!item) {
      return reply.code(404).send({
        error: {
          code: "HISTORY_NOT_FOUND",
          message: `No history entry '${historyId}' was found for this player.`
        }
      });
    }

    const sessionDocument =
      await services.repositories.gameSessions.findOwnedSessionDocument(
        item.sessionId,
        player.playerId
      );
    const loyaltyEntries = await services.repositories.loyaltyLedger.listByPlayerSession(
      player.playerId,
      item.sessionId
    );
    const response: HistoryDetailResponse = {
      item,
      session: sessionDocument
        ? {
            sessionId: sessionDocument.sessionId,
            mode: sessionDocument.mode,
            gameType: sessionDocument.gameType,
            status: sessionDocument.status,
            roomCode: sessionDocument.roomCode,
            hostPlayerId: sessionDocument.hostPlayerId,
            createdAt: sessionDocument.createdAt.toISOString(),
            startedAt: sessionDocument.startedAt?.toISOString(),
            endedAt: sessionDocument.endedAt?.toISOString()
          }
        : undefined,
      result: sessionDocument?.result,
      loyaltyEntries
    };

    return response;
  });
}
