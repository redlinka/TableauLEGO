import {
  LoyaltyBalanceResponse,
  LoyaltyLedgerResponse
} from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { z } from "zod";
import { AppServices } from "../../types.js";
import { resolveAuthenticatedPlayer } from "../auth/requestAuth.js";

const ledgerQuerySchema = z.object({
  limit: z.coerce.number().int().min(1).max(100).default(20)
});

export async function registerLoyaltyRoutes(
  app: FastifyInstance,
  services: AppServices
): Promise<void> {
  app.get("/api/v1/loyalty/balance", async (request, reply) => {
    const player = await resolveAuthenticatedPlayer(request, services);

    if (!player) {
      return reply.code(401).send({
        error: {
          code: "AUTH_REQUIRED",
          message: "Authenticate before requesting loyalty balance."
        }
      });
    }

    const profile = await services.repositories.players.getProfile(player.playerId);

    if (!profile) {
      return reply.code(404).send({
        error: {
          code: "PLAYER_NOT_FOUND",
          message: `Unknown player '${player.playerId}'.`
        }
      });
    }

    const [recentEntries, totalEntries] = await Promise.all([
      services.repositories.loyaltyLedger.listByPlayerId(player.playerId, 10),
      services.repositories.loyaltyLedger.countByPlayerId(player.playerId)
    ]);

    const response: LoyaltyBalanceResponse = {
      summary: {
        ...profile.loyaltySummary,
        totalEntries
      },
      recentEntries
    };

    return response;
  });

  app.get("/api/v1/loyalty/ledger", async (request, reply) => {
    const player = await resolveAuthenticatedPlayer(request, services);

    if (!player) {
      return reply.code(401).send({
        error: {
          code: "AUTH_REQUIRED",
          message: "Authenticate before requesting loyalty ledger."
        }
      });
    }

    const { limit } = ledgerQuerySchema.parse(request.query ?? {});
    const profile = await services.repositories.players.getProfile(player.playerId);

    if (!profile) {
      return reply.code(404).send({
        error: {
          code: "PLAYER_NOT_FOUND",
          message: `Unknown player '${player.playerId}'.`
        }
      });
    }

    const [items, totalEntries] = await Promise.all([
      services.repositories.loyaltyLedger.listByPlayerId(player.playerId, limit),
      services.repositories.loyaltyLedger.countByPlayerId(player.playerId)
    ]);
    const response: LoyaltyLedgerResponse = {
      total: totalEntries,
      items,
      summary: {
        ...profile.loyaltySummary,
        totalEntries
      }
    };

    return response;
  });
}
