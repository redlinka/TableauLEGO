import {
  LoyaltyBalanceResponse,
  LoyaltyEntryType,
  LoyaltyLedgerResponse,
  LoyaltyRedeemResponse
} from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { z } from "zod";
import { AppServices } from "../../types.js";
import { resolveAuthenticatedPlayer } from "../auth/requestAuth.js";
import { fetchLoyaltyPolicy } from "../../domain/common/loyaltyPolicyFetcher.js";

const LOYALTY_TIERS = [
  { points: 200, discountPercent: 5 },
  { points: 500, discountPercent: 10 },
  { points: 1000, discountPercent: 20 }
] as const;

const redeemBodySchema = z.object({
  points: z.number().int().positive(),
  orderRef: z.string().max(64).optional()
});

const REDEEM_RATE_WINDOW_MS = 60_000;
const REDEEM_RATE_MAX = 5;
const redeemRateMap = new Map<string, { count: number; resetAt: number }>();

function checkRedeemRate(playerId: string): boolean {
  const now = Date.now();
  const entry = redeemRateMap.get(playerId);

  if (!entry || now >= entry.resetAt) {
    redeemRateMap.set(playerId, { count: 1, resetAt: now + REDEEM_RATE_WINDOW_MS });
    return true;
  }

  if (entry.count >= REDEEM_RATE_MAX) {
    return false;
  }

  entry.count += 1;
  return true;
}

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

  app.post("/api/v1/loyalty/redeem", async (request, reply) => {
    const player = await resolveAuthenticatedPlayer(request, services);

    if (!player) {
      return reply.code(401).send({
        error: {
          code: "AUTH_REQUIRED",
          message: "Authenticate before redeeming loyalty points."
        }
      });
    }

    if (!checkRedeemRate(player.playerId)) {
      return reply.code(429).send({
        error: {
          code: "RATE_LIMITED",
          message: "Too many redemption attempts. Try again in a minute."
        }
      });
    }

    const body = redeemBodySchema.parse(request.body ?? {});
    const phpPolicy = await fetchLoyaltyPolicy(services.config.phpApiUrl);
    const activeTiers = phpPolicy?.tiers ?? LOYALTY_TIERS;
    const tier = activeTiers.find((t) => t.points === body.points);

    if (!tier) {
      return reply.code(400).send({
        error: {
          code: "INVALID_TIER",
          message: `Points value must match a valid tier (${activeTiers.map((t) => t.points).join(", ")}).`
        }
      });
    }

    const fifoBalance = await services.repositories.loyaltyLedger.getAvailableBalance(
      player.playerId
    );

    if (fifoBalance >= tier.points) {
      await services.repositories.loyaltyLedger.consumePointsFifo(
        player.playerId,
        tier.points
      );
    }

    const updatedSummary = await services.repositories.players.redeemLoyaltyPoints({
      playerId: player.playerId,
      points: tier.points
    });

    if (!updatedSummary) {
      return reply.code(409).send({
        error: {
          code: "INSUFFICIENT_BALANCE",
          message: "Insufficient balance or unknown player."
        }
      });
    }

    await services.repositories.loyaltyLedger.append({
      playerId: player.playerId,
      entryType: LoyaltyEntryType.Redemption,
      pointsDelta: -tier.points,
      balanceAfter: updatedSummary.balance,
      reason: `redemption_${tier.discountPercent}pct_discount`,
      metadata: {
        tier: tier.points,
        discountPercent: tier.discountPercent,
        orderRef: body.orderRef,
        consumedFifo: true
      }
    });

    const response: LoyaltyRedeemResponse = {
      success: true,
      pointsDeducted: tier.points,
      discountPercent: tier.discountPercent,
      balanceAfter: updatedSummary.balance
    };

    return response;
  });
}
