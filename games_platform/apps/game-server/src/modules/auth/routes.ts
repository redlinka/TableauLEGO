import {
  GuestAuthRequest,
  GuestAuthResponse,
  PhpExchangeRequest,
  PhpExchangeResponse
} from "@games-platform/game-contracts";
import { FastifyInstance } from "fastify";
import { z } from "zod";
import { AppServices } from "../../types.js";

const guestAuthBodySchema = z.object({
  preferredAlias: z.string().trim().min(1).max(24).optional()
});

const phpExchangeBodySchema = z.object({
  phpToken: z.string().trim().min(16)
});

export async function registerAuthRoutes(
  app: FastifyInstance,
  services: AppServices
): Promise<void> {
  app.post("/api/v1/auth/guest", async (request, reply) => {
    const body = guestAuthBodySchema
      .partial()
      .parse((request.body ?? {}) as GuestAuthRequest);

    const player = await services.repositories.players.createGuestPlayer(
      body.preferredAlias
    );
    const issuedToken = await services.tokenService.issueGuestToken(player);

    const response: GuestAuthResponse = {
      accessToken: issuedToken.accessToken,
      tokenType: "Bearer",
      expiresAt: issuedToken.expiresAt,
      player
    };

    return reply.send(response);
  });

  app.post("/api/v1/auth/php/exchange", async (request, reply) => {
    if (!services.config.phpGameTokenSecret) {
      return reply.code(503).send({
        error: {
          code: "PHP_BRIDGE_DISABLED",
          message: "PHP signed-token exchange is not configured."
        }
      });
    }

    const body = phpExchangeBodySchema.parse((request.body ?? {}) as PhpExchangeRequest);

    try {
      const exchanged = await services.tokenService.exchangePhpToken(body.phpToken);
      const player = await services.repositories.players.ensureTechnicalPlayer(
        exchanged.identity,
        {
          externalRef: exchanged.externalRef,
          technicalLoyaltyId: exchanged.technicalLoyaltyId
        }
      );

      const response: PhpExchangeResponse = {
        accessToken: exchanged.accessToken,
        tokenType: "Bearer",
        expiresAt: exchanged.expiresAt ?? new Date().toISOString(),
        player,
        bridge: {
          issuer: exchanged.issuer,
          audience: exchanged.audience,
          subjectHash: exchanged.subjectHash ?? "",
          technicalLoyaltyId: exchanged.technicalLoyaltyId
        }
      };

      return reply.send(response);
    } catch {
      return reply.code(401).send({
        error: {
          code: "PHP_TOKEN_INVALID",
          message: "The PHP signed token could not be validated."
        }
      });
    }
  });
}
